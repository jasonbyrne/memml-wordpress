<?php
/**
 * Safe Markdown rendering for feed descriptions.
 *
 * @package Memml
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the simple Markdown organizers write in Memml descriptions.
 *
 * Mirrors memml.com's own description pipeline (GitHub-flavored Markdown with
 * line breaks kept as typed and bare URLs and email addresses turned into
 * links, then sanitized to a short allowlist). Everything in the source is
 * escaped first and only the markup produced here is emitted, so raw HTML in a
 * description is always shown as text. Links accept http, https, and mailto
 * targets only.
 */
final class Memml_Markdown {

	/**
	 * Marks placeholders for already-rendered inline HTML.
	 */
	const TOKEN = "\x1A";

	/**
	 * Deepest nesting of lists and quotes rendered as structure.
	 */
	const MAX_DEPTH = 6;

	/**
	 * Rendered inline fragments, keyed by placeholder number.
	 *
	 * @var string[]
	 */
	private $tokens = array();

	/**
	 * Renders Markdown as safe HTML.
	 *
	 * @param string $text Markdown source.
	 * @return string HTML, or an empty string for empty input.
	 */
	public static function to_html( $text ) {
		$source = self::normalize( $text );

		if ( '' === $source ) {
			return '';
		}

		$renderer = new self();

		return $renderer->render_blocks( explode( "\n", $source ), 0 );
	}

	/**
	 * Renders Markdown as one line of plain text for short summaries.
	 *
	 * Formatting marks are dropped, a link keeps only its text, and paragraphs,
	 * line breaks, and list items run together. The result is text, not HTML,
	 * so it must still be escaped for output.
	 *
	 * @param string $text Markdown source.
	 * @return string
	 */
	public static function to_plain_text( $text ) {
		$html = self::to_html( $text );

		if ( '' === $html ) {
			return '';
		}

		$text = preg_replace( '#<(?:br|hr)>|</?(?:p|ul|ol|li|h[1-6]|blockquote|pre)\b[^>]*>#i', ' ', $html );
		// Every "<" in the source was escaped, so any tag here is our own.
		$text = preg_replace( '/<[^>]*>/', '', $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Normalizes line endings, tabs, and control characters.
	 *
	 * @param mixed $text Markdown source.
	 * @return string
	 */
	private static function normalize( $text ) {
		if ( ! is_scalar( $text ) ) {
			return '';
		}

		$text = str_replace( array( "\r\n", "\r", "\t" ), array( "\n", "\n", '    ' ), (string) $text );
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );

		return trim( (string) $text, "\n" );
	}

	/**
	 * Renders a sequence of lines as block-level HTML.
	 *
	 * @param string[] $lines Source lines.
	 * @param int      $depth Current nesting depth.
	 * @return string
	 */
	private function render_blocks( $lines, $depth ) {
		if ( $depth > self::MAX_DEPTH ) {
			return $this->render_paragraph( $lines );
		}

		$html      = '';
		$paragraph = array();
		$count     = count( $lines );
		$i         = 0;

		while ( $i < $count ) {
			$line = $lines[ $i ];

			if ( '' === trim( $line ) ) {
				$html     .= $this->render_paragraph( $paragraph );
				$paragraph = array();
				++$i;
				continue;
			}

			if ( preg_match( '/^ {0,3}(`{3,}|~{3,})/', $line, $fence ) ) {
				$html     .= $this->render_paragraph( $paragraph );
				$paragraph = array();
				$close     = '/^ {0,3}' . preg_quote( $fence[1][0], '/' ) . '{' . strlen( $fence[1] ) . ',}\s*$/';
				$code      = array();

				++$i;

				while ( $i < $count && ! preg_match( $close, $lines[ $i ] ) ) {
					$code[] = $lines[ $i ];
					++$i;
				}

				++$i;
				$html .= '<pre><code>' . self::escape( implode( "\n", $code ) ) . '</code></pre>';
				continue;
			}

			if ( preg_match( '/^ {0,3}(#{1,6})(?:[ ]+(.*?))?(?:[ ]+#+)?[ ]*$/', $line, $heading ) ) {
				$html     .= $this->render_paragraph( $paragraph );
				$paragraph = array();
				$text      = isset( $heading[2] ) ? trim( $heading[2] ) : '';

				// The details pop-up titles each item with an h3, so headings
				// inside a description start one level below it.
				$level = min( 6, strlen( $heading[1] ) + 3 );
				$html .= '' === $text ? '' : '<h' . $level . '>' . $this->render_inline( $text ) . '</h' . $level . '>';
				++$i;
				continue;
			}

			if ( preg_match( '/^ {0,3}([-*_])(?:[ ]*\1){2,}[ ]*$/', $line ) ) {
				$html     .= $this->render_paragraph( $paragraph ) . '<hr>';
				$paragraph = array();
				++$i;
				continue;
			}

			if ( preg_match( '/^ {0,3}>/', $line ) ) {
				$html     .= $this->render_paragraph( $paragraph );
				$paragraph = array();
				$quoted    = array();

				while ( $i < $count && preg_match( '/^ {0,3}> ?(.*)$/', $lines[ $i ], $quote ) ) {
					$quoted[] = $quote[1];
					++$i;
				}

				$html .= '<blockquote>' . $this->render_blocks( $quoted, $depth + 1 ) . '</blockquote>';
				continue;
			}

			$item = self::match_list_item( $line );

			// As in CommonMark, only a list starting at 1 can interrupt a
			// paragraph, so a sentence that merely begins "2024." stays text.
			if ( $item && ( empty( $paragraph ) || 'ul' === $item['type'] || 1 === $item['start'] ) ) {
				$html     .= $this->render_paragraph( $paragraph );
				$paragraph = array();
				$html     .= $this->render_list( $lines, $i, $depth );
				continue;
			}

			$paragraph[] = $line;
			++$i;
		}

		return $html . $this->render_paragraph( $paragraph );
	}

	/**
	 * Matches a list item marker.
	 *
	 * @param string $line Source line.
	 * @return array|null Item type, start number, and content, or null.
	 */
	private static function match_list_item( $line ) {
		if ( preg_match( '/^ {0,3}[-*+][ ]+(.*)$/', $line, $found ) ) {
			return array(
				'type'    => 'ul',
				'start'   => 1,
				'content' => $found[1],
			);
		}

		if ( preg_match( '/^ {0,3}(\d{1,9})[.)][ ]+(.*)$/', $line, $found ) ) {
			return array(
				'type'    => 'ol',
				'start'   => (int) $found[1],
				'content' => $found[2],
			);
		}

		return null;
	}

	/**
	 * Renders a list starting at the given line and advances past it.
	 *
	 * Lines indented by at least two spaces belong to the item above, so
	 * nested lists and extra paragraphs work; an unindented line directly
	 * after an item continues its text.
	 *
	 * @param string[] $lines Source lines.
	 * @param int      $i     Index of the first item; updated to the next line.
	 * @param int      $depth Current nesting depth.
	 * @return string
	 */
	private function render_list( $lines, &$i, $depth ) {
		$count = count( $lines );
		$first = self::match_list_item( $lines[ $i ] );
		$type  = $first['type'];
		$items = array();
		$loose = false;

		while ( $i < $count ) {
			$item = self::match_list_item( $lines[ $i ] );

			if ( ! $item || $item['type'] !== $type ) {
				break;
			}

			$body = array( $item['content'] );

			for ( ++$i; $i < $count; ++$i ) {
				$line = $lines[ $i ];

				if ( '' === trim( $line ) ) {
					$next = $i + 1;

					while ( $next < $count && '' === trim( $lines[ $next ] ) ) {
						++$next;
					}

					if ( $next < $count && preg_match( '/^ {2,}\S/', $lines[ $next ] ) ) {
						$loose = true;
						$body  = array_merge( $body, array_fill( 0, $next - $i, '' ) );
						$i     = $next - 1;
						continue;
					}

					$following = $next < $count ? self::match_list_item( $lines[ $next ] ) : null;

					if ( $following && $following['type'] === $type ) {
						$loose = true;
						$i     = $next;
					}

					break;
				}

				if ( preg_match( '/^ {2,}/', $line ) ) {
					$body[] = preg_replace( '/^ {2,4}/', '', $line );
					continue;
				}

				if ( self::match_list_item( $line ) || self::starts_block( $line ) ) {
					break;
				}

				$body[] = $line;
			}

			$items[] = $body;
		}

		$html = '';

		foreach ( $items as $body ) {
			$content = $this->render_blocks( $body, $depth + 1 );

			// Tight lists keep item text inline, as Markdown renderers do.
			if ( ! $loose && 0 === strpos( $content, '<p>' ) ) {
				$end     = strpos( $content, '</p>' );
				$content = substr( $content, 3, $end - 3 ) . substr( $content, $end + 4 );
			}

			$html .= '<li>' . $content . '</li>';
		}

		$start = 'ol' === $type && 1 !== $first['start'] ? ' start="' . $first['start'] . '"' : '';

		return '<' . $type . $start . '>' . $html . '</' . $type . '>';
	}

	/**
	 * Whether a line starts a block other than a list item.
	 *
	 * @param string $line Source line.
	 * @return bool
	 */
	private static function starts_block( $line ) {
		return (bool) preg_match( '/^ {0,3}(?:`{3,}|~{3,}|#{1,6}(?:[ ]|$)|>|([-*_])(?:[ ]*\1){2,}[ ]*$)/', $line );
	}

	/**
	 * Renders collected paragraph lines.
	 *
	 * @param string[] $lines Paragraph lines.
	 * @return string
	 */
	private function render_paragraph( $lines ) {
		$text = trim( implode( "\n", array_map( 'trim', $lines ) ) );

		return '' === $text ? '' : '<p>' . $this->render_inline( $text ) . '</p>';
	}

	/**
	 * Renders inline Markdown: code, links, automatic links, and emphasis.
	 *
	 * Code spans, links, and URLs become placeholders before the remaining
	 * text is escaped, so emphasis marks inside them are left alone.
	 *
	 * @param string $text        Inline source.
	 * @param bool   $allow_links Whether links may be created (not inside link text).
	 * @return string
	 */
	private function render_inline( $text, $allow_links = true ) {
		$text = self::replace(
			'/(`+)(.+?)(?<!`)\1(?!`)/s',
			function ( $found ) {
				$code = preg_match( '/^ .* $/s', $found[2] ) && '' !== trim( $found[2] ) ? substr( $found[2], 1, -1 ) : $found[2];

				return $this->token( '<code>' . self::escape( $code ) . '</code>' );
			},
			$text
		);

		$text = self::replace(
			'/\\\\([!-\/:-@\[-`{-~])/',
			function ( $found ) {
				return $this->token( self::escape( $found[1] ) );
			},
			$text
		);

		if ( $allow_links ) {
			$text = $this->tokenize_links( $text );
		}

		$html = self::escape( $text );
		$html = self::replace( '/\*\*\*(?=\S)(.+?)(?<=\S)\*\*\*/s', '<strong><em>$1</em></strong>', $html );
		$html = self::replace( '/\*\*(?=\S)(.+?)(?<=\S)\*\*/s', '<strong>$1</strong>', $html );
		$html = self::replace( '/(?<!\w)__(?=\S)(.+?)(?<=\S)__(?!\w)/su', '<strong>$1</strong>', $html );
		$html = self::replace( '/(?<!\*)\*(?=[^\s*])(.+?)(?<=[^\s*])\*(?!\*)/s', '<em>$1</em>', $html );
		$html = self::replace( '/(?<!\w)_(?=[^\s_])(.+?)(?<=[^\s_])_(?!\w)/su', '<em>$1</em>', $html );
		$html = str_replace( "\n", "<br>\n", $html );

		return self::replace(
			'/' . self::TOKEN . '(\d+)' . self::TOKEN . '/',
			function ( $found ) {
				return isset( $this->tokens[ $found[1] ] ) ? $this->tokens[ $found[1] ] : '';
			},
			$html
		);
	}

	/**
	 * Replaces Markdown links, autolinks, bare URLs, and emails with placeholders.
	 *
	 * @param string $text Inline source.
	 * @return string
	 */
	private function tokenize_links( $text ) {
		// <https://example.com> and <name@example.com>.
		$text = self::replace(
			'/<((?:https?:\/\/|mailto:)[^\s<>]+|[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})>/i',
			function ( $found ) {
				$href = false === strpos( $found[1], ':' ) ? 'mailto:' . $found[1] : self::safe_href( $found[1] );

				return '' === $href ? $found[0] : $this->token( self::anchor( $href, self::escape( $found[1] ) ) );
			},
			$text
		);

		// [text](url "title") and ![alt](url). Images are not embedded; they
		// become links so the picture stays one click away.
		$text = self::replace(
			'/(!?)\[((?:[^\[\]]|\[[^\[\]]*\])*)\]\(\s*<?([^\s()<>]*(?:\([^\s()<>]*\)[^\s()<>]*)*)>?(?:\s+(?:"([^"]*)"|\'([^\']*)\'))?\s*\)/',
			function ( $found ) {
				$href  = self::safe_href( $found[3] );
				$label = '' === trim( $found[2] ) && '!' === $found[1] ? $found[3] : $found[2];
				$label = $this->render_inline( $label, false );

				if ( '' === $href ) {
					return $this->token( $label );
				}

				$title = isset( $found[5] ) && '' !== $found[5] ? $found[5] : ( isset( $found[4] ) ? $found[4] : '' );

				return $this->token( self::anchor( $href, '' === $label ? self::escape( $href ) : $label, $title ) );
			},
			$text
		);

		// Bare https://… and www.… addresses, with trailing punctuation and
		// unbalanced closing parentheses left outside the link as in GFM.
		$text = self::replace(
			'/(?<![\w@\/.:-])(?:https?:\/\/|www\.)[^\s<\x1A]+/i',
			function ( $found ) {
				$url   = $found[0];
				$trail = '';

				while ( '' !== $url ) {
					$last = substr( $url, -1 );

					if ( false !== strpos( '?!.,:;*_~\'"]', $last ) || ( ')' === $last && substr_count( $url, ')' ) > substr_count( $url, '(' ) ) ) {
						$trail = $last . $trail;
						$url   = substr( $url, 0, -1 );
						continue;
					}

					break;
				}

				$href = self::safe_href( $url );

				return '' === $href ? $found[0] : $this->token( self::anchor( $href, self::escape( $url ) ) ) . $trail;
			},
			$text
		);

		// Bare email addresses.
		return self::replace(
			'/(?<![\w.+\/-])[A-Za-z0-9._%+-]+@[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?)+/',
			function ( $found ) {
				return $this->token( self::anchor( 'mailto:' . $found[0], self::escape( $found[0] ) ) );
			},
			$text
		);
	}

	/**
	 * Stores rendered HTML and returns its placeholder.
	 *
	 * @param string $html Rendered HTML.
	 * @return string
	 */
	private function token( $html ) {
		$this->tokens[] = $html;

		return self::TOKEN . ( count( $this->tokens ) - 1 ) . self::TOKEN;
	}

	/**
	 * Returns a link target when it is an absolute http(s) or mailto URL.
	 *
	 * @param string $url Link target from the source.
	 * @return string The usable URL, or an empty string.
	 */
	private static function safe_href( $url ) {
		$url = trim( $url );

		if ( '' === $url || false !== strpos( $url, self::TOKEN ) ) {
			return '';
		}

		if ( preg_match( '/^www\./i', $url ) ) {
			$url = 'http://' . $url;
		}

		return preg_match( '#^(?:https?://[^\s/?\#.]+(?:\.[^\s/?\#.]+)*\.?(?:[:/?\#]|$)|mailto:[^\s@]+@[^\s@]+$)#i', $url ) ? $url : '';
	}

	/**
	 * Builds a link element.
	 *
	 * @param string $href  Validated URL.
	 * @param string $label Link HTML.
	 * @param string $title Optional title text.
	 * @return string
	 */
	private static function anchor( $href, $label, $title = '' ) {
		$attributes = '' === $title ? '' : ' title="' . self::escape( $title ) . '"';

		// Outbound links open in a new tab like the plugin's other external links.
		if ( 0 !== stripos( $href, 'mailto:' ) ) {
			$attributes .= ' rel="noopener noreferrer" target="_blank"';
		}

		return '<a href="' . self::escape( $href ) . '"' . $attributes . '>' . $label . '</a>';
	}

	/**
	 * Escapes text for HTML content or attribute values.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private static function escape( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}

	/**
	 * Runs a regular-expression replacement, keeping the input if PCRE fails.
	 *
	 * @param string          $pattern     Pattern.
	 * @param string|callable $replacement Replacement string or callback.
	 * @param string          $subject     Input.
	 * @return string
	 */
	private static function replace( $pattern, $replacement, $subject ) {
		$result = is_callable( $replacement )
			? preg_replace_callback( $pattern, $replacement, $subject )
			: preg_replace( $pattern, $replacement, $subject );

		return null === $result ? $subject : $result;
	}
}

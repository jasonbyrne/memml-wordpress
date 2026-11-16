<?php
/**
 * Markdown renderer unit tests.
 *
 * @package Memml
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests description Markdown rendering and its safety guarantees.
 */
final class Memml_Markdown_Test extends TestCase {

	/**
	 * Empty and non-string input renders nothing.
	 *
	 * @return void
	 */
	public function test_empty_input_renders_nothing() {
		$this->assertSame( '', Memml_Markdown::to_html( '' ) );
		$this->assertSame( '', Memml_Markdown::to_html( " \n\n " ) );
		$this->assertSame( '', Memml_Markdown::to_html( null ) );
		$this->assertSame( '', Memml_Markdown::to_html( array( 'x' ) ) );
		$this->assertSame( '', Memml_Markdown::to_plain_text( '' ) );
	}

	/**
	 * Paragraphs split on blank lines and single line breaks are kept.
	 *
	 * @return void
	 */
	public function test_paragraphs_and_line_breaks() {
		$this->assertSame(
			"<p>First line<br>\nsecond line</p><p>Next paragraph</p>",
			Memml_Markdown::to_html( "First line\r\nsecond line\r\n\r\nNext paragraph" )
		);
	}

	/**
	 * Bare URLs, www addresses, and emails become links, as on memml.com.
	 *
	 * @return void
	 */
	public function test_bare_urls_and_emails_become_links() {
		$html = Memml_Markdown::to_html( "More info:\nhttps://runsignup.com/TicketEvent/CarolsAndCocoa2026" );

		$this->assertSame(
			"<p>More info:<br>\n<a href=\"https://runsignup.com/TicketEvent/CarolsAndCocoa2026\" rel=\"noopener noreferrer\" target=\"_blank\">https://runsignup.com/TicketEvent/CarolsAndCocoa2026</a></p>",
			$html
		);

		$html = Memml_Markdown::to_html( 'Visit www.example.com. Questions? Email help@example.org!' );

		$this->assertStringContainsString( '<a href="http://www.example.com" rel="noopener noreferrer" target="_blank">www.example.com</a>. Questions?', $html );
		$this->assertStringContainsString( '<a href="mailto:help@example.org">help@example.org</a>!', $html );
	}

	/**
	 * Trailing punctuation and unbalanced parentheses stay outside links.
	 *
	 * @return void
	 */
	public function test_bare_url_trailing_punctuation() {
		$html = Memml_Markdown::to_html( 'See (https://example.com/a_(b)), then https://example.com/x?y=1&z=2.' );

		$this->assertStringContainsString( '(<a href="https://example.com/a_(b)" rel="noopener noreferrer" target="_blank">https://example.com/a_(b)</a>),', $html );
		$this->assertStringContainsString( '<a href="https://example.com/x?y=1&amp;z=2" rel="noopener noreferrer" target="_blank">https://example.com/x?y=1&amp;z=2</a>.', $html );
	}

	/**
	 * Underscores and asterisks inside URLs are not treated as emphasis.
	 *
	 * @return void
	 */
	public function test_urls_are_not_emphasized() {
		$html = Memml_Markdown::to_html( 'https://example.com/a_b_c and https://example.com/*x*' );

		$this->assertStringNotContainsString( '<em>', $html );
		$this->assertStringContainsString( 'href="https://example.com/a_b_c"', $html );
	}

	/**
	 * Inline formatting covers bold, italic, code, and Markdown links.
	 *
	 * @return void
	 */
	public function test_inline_formatting() {
		$this->assertSame(
			'<p><strong>Bold</strong>, <strong>also</strong>, <em>italic</em>, <em>also</em>, snake_case_name, <code>a *b*</code>, *literal*, and <a href="https://example.com" title="Site" rel="noopener noreferrer" target="_blank">a <strong>link</strong></a>.</p>',
			Memml_Markdown::to_html( '**Bold**, __also__, *italic*, _also_, snake_case_name, `a *b*`, \*literal\*, and [a **link**](https://example.com "Site").' )
		);
	}

	/**
	 * Headings sit below the pop-up's own h3 title.
	 *
	 * @return void
	 */
	public function test_headings_start_below_the_details_title() {
		$this->assertSame(
			'<h4>Schedule</h4><h5>Morning</h5><h6>Deep</h6><p>#hashtag</p>',
			Memml_Markdown::to_html( "# Schedule\n## Morning\n#### Deep\n\n#hashtag" )
		);
	}

	/**
	 * Unordered, ordered, nested, and loose lists render as lists.
	 *
	 * @return void
	 */
	public function test_lists() {
		$this->assertSame(
			'<ul><li>One</li><li>Two<ul><li>Nested</li></ul></li><li>Three</li></ul>',
			Memml_Markdown::to_html( "- One\n* Two\n  - Nested\n+ Three" )
		);
		$this->assertSame(
			'<ol start="3"><li>Three</li><li>Four</li></ol>',
			Memml_Markdown::to_html( "3. Three\n4. Four" )
		);
		$this->assertSame(
			'<ol><li><p>A</p></li><li><p>B</p></li></ol>',
			Memml_Markdown::to_html( "1. A\n\n2. B" )
		);
		$this->assertSame(
			'<p>Bring:</p><ul><li>Gloves</li><li>Water</li></ul><p>Thanks!</p>',
			Memml_Markdown::to_html( "Bring:\n- Gloves\n- Water\n\nThanks!" )
		);
	}

	/**
	 * A sentence starting with a number does not interrupt a paragraph.
	 *
	 * @return void
	 */
	public function test_numbers_mid_paragraph_stay_text() {
		$this->assertSame(
			"<p>Founded in<br>\n2024. Still going.</p>",
			Memml_Markdown::to_html( "Founded in\n2024. Still going." )
		);
	}

	/**
	 * Quotes, rules, and fenced code render as blocks.
	 *
	 * @return void
	 */
	public function test_quotes_rules_and_code() {
		$this->assertSame(
			"<blockquote><p>Quoted <em>text</em><br>\nline two</p></blockquote><hr><pre><code>&lt;b&gt;raw&lt;/b&gt;\n  kept</code></pre>",
			Memml_Markdown::to_html( "> Quoted *text*\n> line two\n\n---\n\n```\n<b>raw</b>\n  kept\n```" )
		);
	}

	/**
	 * Images become links instead of being embedded.
	 *
	 * @return void
	 */
	public function test_images_become_links() {
		$this->assertSame(
			'<p><a href="https://example.com/flyer.png" rel="noopener noreferrer" target="_blank">Flyer</a></p>',
			Memml_Markdown::to_html( '![Flyer](https://example.com/flyer.png)' )
		);
	}

	/**
	 * Raw HTML is escaped and unsafe link targets are dropped.
	 *
	 * @return void
	 */
	public function test_html_and_unsafe_links_are_neutralized() {
		$html = Memml_Markdown::to_html( '<script>alert(1)</script> <img src=x onerror=alert(1)> [click](javascript:alert(1)) [rel](/relative) [data](data:text/html,x) "quotes" & \'apostrophes\'' );

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( 'href', $html );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
		$this->assertStringContainsString( ' click ', $html );
		$this->assertStringContainsString( '&quot;quotes&quot; &amp; &#039;apostrophes&#039;', $html );
	}

	/**
	 * Attribute values cannot break out of the href.
	 *
	 * @return void
	 */
	public function test_link_attributes_are_escaped() {
		$html = Memml_Markdown::to_html( 'https://example.com/"onmouseover="alert(1)' );

		$this->assertStringContainsString( 'href="https://example.com/&quot;onmouseover=&quot;alert(1)"', $html );
		$this->assertStringNotContainsString( '" onmouseover', $html );
	}

	/**
	 * Placeholder control characters in the source are ignored.
	 *
	 * @return void
	 */
	public function test_placeholder_characters_in_source_are_stripped() {
		$this->assertSame( '<p>a0b</p>', Memml_Markdown::to_html( "a\x1A0\x1Ab" ) );
	}

	/**
	 * Deep nesting is capped rather than recursing without limit.
	 *
	 * @return void
	 */
	public function test_deep_nesting_is_capped() {
		$html = Memml_Markdown::to_html( str_repeat( '>', 500 ) . ' deep' );

		$this->assertSame( 7, substr_count( $html, '<blockquote>' ) );
		$this->assertStringContainsString( 'deep', $html );
	}

	/**
	 * Plain text drops formatting and keeps link text.
	 *
	 * @return void
	 */
	public function test_plain_text_summary() {
		$this->assertSame(
			'Schedule Bring gloves and water. Sign up at the site or https://example.com/x. A & B <ok>',
			Memml_Markdown::to_plain_text( "# Schedule\n\n- Bring **gloves**\n  - and *water*.\n\nSign up at [the site](https://example.com) or https://example.com/x.\nA & B <ok>" )
		);
	}
}

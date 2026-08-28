import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import eventsMetadata from '../blocks/events/block.json';
import volunteersMetadata from '../blocks/volunteers/block.json';

const createFeedEdit = ( label, instructions ) =>
	function FeedEdit() {
		return (
			<div { ...useBlockProps() }>
				<Placeholder icon="admin-site-alt3" label={ label }>
					{ instructions }
				</Placeholder>
			</div>
		);
	};

registerBlockType( eventsMetadata.name, {
	edit: createFeedEdit(
		__( 'Memml Events', 'memml' ),
		__(
			'Events will use the organization configured in Settings → Memml.',
			'memml'
		)
	),
	save: () => null,
} );

registerBlockType( volunteersMetadata.name, {
	edit: createFeedEdit(
		__( 'Memml Volunteers', 'memml' ),
		__(
			'Volunteer opportunities will use the organization configured in Settings → Memml.',
			'memml'
		)
	),
	save: () => null,
} );

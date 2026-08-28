import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, Placeholder, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import calendarMetadata from '../blocks/calendar/block.json';
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

const CalendarEdit = ( { attributes, setAttributes } ) => (
	<>
		<InspectorControls>
			<PanelBody title={ __( 'Calendar settings', 'memml' ) }>
				<SelectControl
					label={ __( 'Default view', 'memml' ) }
					value={ attributes.defaultView }
					options={ [
						{ label: __( 'Events', 'memml' ), value: 'events' },
						{
							label: __( 'Volunteer Opportunities', 'memml' ),
							value: 'volunteers',
						},
					] }
					onChange={ ( defaultView ) =>
						setAttributes( { defaultView } )
					}
				/>
			</PanelBody>
		</InspectorControls>
		<div { ...useBlockProps() }>
			<Placeholder
				icon="calendar"
				label={ __( 'Memml Calendar', 'memml' ) }
			>
				{ __(
					'Visitors can switch between events and volunteer opportunities.',
					'memml'
				) }
			</Placeholder>
		</div>
	</>
);

registerBlockType( calendarMetadata.name, {
	edit: CalendarEdit,
	save: () => null,
} );

registerBlockType( eventsMetadata.name, {
	edit: createFeedEdit(
		__( 'Memml Events', 'memml' ),
		__(
			'Events will use the organization configured in Settings → Memml Calendar.',
			'memml'
		)
	),
	save: () => null,
} );

registerBlockType( volunteersMetadata.name, {
	edit: createFeedEdit(
		__( 'Memml Volunteers', 'memml' ),
		__(
			'Volunteer opportunities will use the organization configured in Settings → Memml Calendar.',
			'memml'
		)
	),
	save: () => null,
} );

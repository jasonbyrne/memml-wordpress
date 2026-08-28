import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, Placeholder, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import calendarMetadata from '../blocks/calendar/block.json';
import eventsMetadata from '../blocks/events/block.json';
import volunteersMetadata from '../blocks/volunteers/block.json';

const LayoutControl = ( { value, onChange } ) => (
	<SelectControl
		label={ __( 'Initially selected view', 'memml' ) }
		value={ value }
		options={ [
			{ label: __( 'List', 'memml' ), value: 'list' },
			{ label: __( 'Month', 'memml' ), value: 'month' },
		] }
		onChange={ onChange }
	/>
);

const PeriodControl = ( { value, onChange } ) => (
	<SelectControl
		label={ __( 'Initially selected list filter', 'memml' ) }
		value={ value }
		options={ [
			{ label: __( 'Upcoming', 'memml' ), value: 'upcoming' },
			{ label: __( 'Past', 'memml' ), value: 'past' },
		] }
		onChange={ onChange }
	/>
);

const createFeedEdit = ( label, instructions ) =>
	function FeedEdit( { attributes, setAttributes } ) {
		return (
			<>
				<InspectorControls>
					<PanelBody title={ __( 'Calendar settings', 'memml' ) }>
						<LayoutControl
							value={ attributes.view }
							onChange={ ( view ) => setAttributes( { view } ) }
						/>
						<PeriodControl
							value={ attributes.period }
							onChange={ ( period ) =>
								setAttributes( { period } )
							}
						/>
					</PanelBody>
				</InspectorControls>
				<div { ...useBlockProps() }>
					<Placeholder icon="admin-site-alt3" label={ label }>
						{ instructions }
					</Placeholder>
				</div>
			</>
		);
	};
const CalendarEdit = ( { attributes, setAttributes } ) => (
	<>
		<InspectorControls>
			<PanelBody title={ __( 'Calendar settings', 'memml' ) }>
				<SelectControl
					label={ __( 'Initially selected calendar', 'memml' ) }
					value={ attributes.calendar }
					options={ [
						{ label: __( 'Events', 'memml' ), value: 'events' },
						{
							label: __( 'Volunteer Opportunities', 'memml' ),
							value: 'volunteers',
						},
					] }
					onChange={ ( calendar ) => setAttributes( { calendar } ) }
				/>
				<LayoutControl
					value={ attributes.view }
					onChange={ ( view ) => setAttributes( { view } ) }
				/>
				<PeriodControl
					value={ attributes.period }
					onChange={ ( period ) => setAttributes( { period } ) }
				/>
			</PanelBody>
		</InspectorControls>
		<div { ...useBlockProps() }>
			<Placeholder
				icon="calendar"
				label={ __( 'Memml Calendar', 'memml' ) }
			>
				{ __(
					'Visitors can switch calendars and choose a list or month view.',
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

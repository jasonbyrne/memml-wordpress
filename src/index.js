import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Disabled,
	ExternalLink,
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

import calendarMetadata from '../blocks/calendar/block.json';
import eventsMetadata from '../blocks/events/block.json';
import volunteersMetadata from '../blocks/volunteers/block.json';

const editorConfig = window.memmlEditor || {};

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

const LimitControl = ( { value, onChange } ) => (
	<RangeControl
		label={ __( 'Maximum items in list view', 'memml' ) }
		help={ __(
			'0 shows every item. Month view always shows every item.',
			'memml'
		) }
		value={ value }
		min={ 0 }
		max={ 50 }
		allowReset
		resetFallbackValue={ 0 }
		onChange={ ( limit ) => onChange( limit || 0 ) }
	/>
);

const UrlKeyControl = ( { value, onChange } ) => (
	<TextControl
		label={ __( 'Share-link identifier', 'memml' ) }
		help={ __(
			'Optional. Use a short, unique identifier when a page contains more than one Memml calendar.',
			'memml'
		) }
		value={ value }
		onChange={ ( urlKey ) =>
			onChange( urlKey.toLowerCase().replace( /[^a-z0-9_-]/g, '' ) )
		}
	/>
);

/**
 * Sends the editor to the settings screen when no organization key is saved.
 *
 * @param {Object} props       Component props.
 * @param {string} props.icon  Placeholder icon.
 * @param {string} props.label Placeholder label.
 * @return {Element} Actionable setup placeholder.
 */
const SetupPlaceholder = ( { icon, label } ) => (
	<Placeholder
		icon={ icon }
		label={ label }
		instructions={ __(
			'No Memml organization key is saved yet, so this block will not display anything on the page.',
			'memml'
		) }
	>
		<ExternalLink href={ editorConfig.settingsUrl || '' }>
			{ __( 'Open Memml Calendar settings', 'memml' ) }
		</ExternalLink>
	</Placeholder>
);

/**
 * Shows the real front-end markup, using the same renderer visitors get.
 *
 * The preview is wrapped in Disabled so its links cannot navigate the editor
 * away from the post.
 *
 * @param {Object} props            Component props.
 * @param {string} props.name       Registered block name.
 * @param {Object} props.attributes Current block attributes.
 * @param {string} props.icon       Placeholder icon.
 * @param {string} props.label      Placeholder label.
 * @return {Element} Server-rendered preview or a setup placeholder.
 */
const CalendarPreview = ( { name, attributes, icon, label } ) => {
	if ( ! editorConfig.isConfigured ) {
		return <SetupPlaceholder icon={ icon } label={ label } />;
	}

	return (
		<Disabled>
			<ServerSideRender
				block={ name }
				attributes={ attributes }
				LoadingResponsePlaceholder={ () => (
					<Placeholder icon={ icon } label={ label }>
						<Spinner />
					</Placeholder>
				) }
				EmptyResponsePlaceholder={ () => (
					<Placeholder
						icon={ icon }
						label={ label }
						instructions={ __(
							'Memml returned nothing to display for these settings.',
							'memml'
						) }
					/>
				) }
				ErrorResponsePlaceholder={ () => (
					<Placeholder
						icon={ icon }
						label={ label }
						instructions={ __(
							'This preview could not be loaded. The calendar may still work on the published page.',
							'memml'
						) }
					/>
				) }
			/>
		</Disabled>
	);
};

const createFeedEdit = ( metadata, icon, label ) =>
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
						<LimitControl
							value={ attributes.limit }
							onChange={ ( limit ) => setAttributes( { limit } ) }
						/>
						<UrlKeyControl
							value={ attributes.urlKey }
							onChange={ ( urlKey ) =>
								setAttributes( { urlKey } )
							}
						/>
					</PanelBody>
				</InspectorControls>
				<div { ...useBlockProps() }>
					<CalendarPreview
						name={ metadata.name }
						attributes={ attributes }
						icon={ icon }
						label={ label }
					/>
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
				<LimitControl
					value={ attributes.limit }
					onChange={ ( limit ) => setAttributes( { limit } ) }
				/>
				<UrlKeyControl
					value={ attributes.urlKey }
					onChange={ ( urlKey ) => setAttributes( { urlKey } ) }
				/>
			</PanelBody>
		</InspectorControls>
		<div { ...useBlockProps() }>
			<CalendarPreview
				name={ calendarMetadata.name }
				attributes={ attributes }
				icon="calendar"
				label={ __( 'Memml Calendar', 'memml' ) }
			/>
		</div>
	</>
);

registerBlockType( calendarMetadata.name, {
	edit: CalendarEdit,
	save: () => null,
} );

registerBlockType( eventsMetadata.name, {
	edit: createFeedEdit(
		eventsMetadata,
		'calendar-alt',
		__( 'Memml Events', 'memml' )
	),
	save: () => null,
} );

registerBlockType( volunteersMetadata.name, {
	edit: createFeedEdit(
		volunteersMetadata,
		'groups',
		__( 'Memml Volunteers', 'memml' )
	),
	save: () => null,
} );

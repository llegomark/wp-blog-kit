/**
 * Stylelint: WordPress's CSS coding standards (@wordpress/stylelint-config), with two adjustments.
 */
export default {
	extends: '@wordpress/stylelint-config',
	rules: {
		// Our classes are lowercase words joined by hyphens, with __ for elements and -- for
		// modifiers. column-* classes belong to WordPress core, which names list-table columns
		// after their key (the plugin's SEO column is column-tala_seo).
		'selector-class-pattern': [
			'^(?:column-[a-z0-9_-]+|[a-z][a-z0-9]*(?:(?:__|--|-)[a-z0-9]+)*)$',
			{
				message:
					'Class names are lowercase words joined by hyphens, with __ for elements and -- for modifiers (selector-class-pattern)',
			},
		],
		// The theme's CSS is grouped by component, and selectors from different components never
		// match the same element (header links and breadcrumb links, the author photo and list
		// thumbnails). The order this rule asks for would scatter the components without changing
		// how any element looks.
		'no-descending-specificity': null,
	},
	overrides: [
		{
			// Google's search widget names its own classes (gsc-webResult, gs-visibleUrl): these
			// styles can only select them as Google wrote them.
			files: [ 'tala-ads/assets/google-search.css' ],
			rules: {
				'selector-class-pattern': null,
			},
		},
		{
			// Gravity Forms names its own classes (gform_wrapper, gfield_required): these styles can
			// only select them as Gravity Forms wrote them.
			files: [ 'tala-blog/assets/gravity-forms-*.css' ],
			rules: {
				'selector-class-pattern': null,
			},
		},
	],
};

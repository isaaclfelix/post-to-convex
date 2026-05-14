/**
 * Extends `@wordpress/scripts` default webpack config with extra entries for
 * other block-editor-only scripts (`build/editor.js`).
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config.js' );

/**
 * @param {import('webpack').Configuration} config Base configuration from `@wordpress/scripts`.
 * @return {import('webpack').Configuration} The same configuration with an `editor` entry merged in.
 */
function withAdditionalEntries( config ) {
	const originalEntry = config.entry;

	return {
		...config,
		entry: () => {
			const entries =
				typeof originalEntry === 'function'
					? originalEntry()
					: { ...originalEntry };

			return {
				...entries,
				editor: path.resolve( process.cwd(), 'src', 'editor.js' ),
			};
		},
	};
}

module.exports = Array.isArray( defaultConfig )
	? defaultConfig.map( withAdditionalEntries )
	: withAdditionalEntries( defaultConfig );

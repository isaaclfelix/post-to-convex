import { createRequire } from 'module';

const require = createRequire( import.meta.url );
const wordpress = require( '@wordpress/eslint-plugin' );
const simpleImportSort = require( 'eslint-plugin-simple-import-sort' );

/** @type {import('eslint').Linter.Config[]} */
export default [
	{
		ignores: [
			'**/node_modules/**',
			'**/build/**',
			'**/dist/**',
			'**/vendor/**',
			'**/tmp/**',
			'**/*.min.js',
		],
	},
	...wordpress.configs.recommended,
	{
		plugins: {
			'simple-import-sort': simpleImportSort,
		},
		rules: {
			'simple-import-sort/imports': 'error',
			'simple-import-sort/exports': 'error',
		},
	},
	{
		files: [ 'wp-content/**/*.{js,mjs,cjs,jsx,ts,tsx}' ],
		rules: {
			// `@wordpress/*` and other packages are often externals from core, not listed in package.json.
			'import/no-unresolved': 'off',
		},
	},
];

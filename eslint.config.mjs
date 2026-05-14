import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { createRequire } from 'module';

const __dirname = path.dirname( fileURLToPath( import.meta.url ) );
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
	{
		files: [
			'wp-content/plugins/post-to-convex/**/*.{js,mjs,cjs,jsx,ts,tsx}',
		],
		rules: {
			// Plugin has its own package.json (e.g. zod); root lint cwd would otherwise flag deps as extraneous.
			'import/no-extraneous-dependencies': [
				'error',
				{
					packageDir: path.join(
						__dirname,
						'wp-content/plugins/post-to-convex'
					),
				},
			],
		},
	},
];

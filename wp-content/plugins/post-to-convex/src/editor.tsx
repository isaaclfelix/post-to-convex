/**
 * Block editor script (enqueued only in the editor).
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, PanelBody } from '@wordpress/components';
import domReady from '@wordpress/dom-ready';
import { PluginSidebar } from '@wordpress/editor';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';

import {
	type CreatePostEndpointSchema,
	createPostEndpointSchema,
} from './schemas';

function PostToConvexSidebar() {
	const [ isPosting, setIsPosting ] = useState< boolean >( false );

	/**
	 * Handle the post to Convex.
	 */
	const handlePost = async () => {
		console.debug( 'handlePost' );
		setIsPosting( true );

		try {
			const payload: CreatePostEndpointSchema = {
				title: 'Hello, world!',
				slug: 'hello-world',
				content: 'Hello, world!',
				excerpt: 'Hello, world!',
				type: 'post',
				status: 'publish',
				commentStatus: 'open',
				createdAt: new Date().toISOString(),
				updatedAt: new Date().toISOString(),
				originalId: 1,
				authorId: 1,
				categoryIds: [ 1 ],
				tagIds: [ 1 ],
			};

			const validatedPayload =
				createPostEndpointSchema.safeParse( payload );

			if ( ! validatedPayload.success ) {
				console.debug( validatedPayload.error );
				setIsPosting( false );
				return;
			}

			const response = await apiFetch( {
				path: '/post-to-convex/v1/createPost',
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify( validatedPayload.data ),
			} );

			console.debug( response );
		} catch ( error ) {
			console.debug( error );
		}

		setIsPosting( false );
	};

	return (
		<PluginSidebar
			name="post-to-convex-sidebar"
			title={ __( 'Post to Convex' ) }
			icon={ 'smiley' }
		>
			<PanelBody>
				<Button
					variant="primary"
					onClick={ handlePost }
					disabled={ isPosting }
				>
					{ isPosting
						? __( 'Posting to Convex…' )
						: __( 'Post to Convex' ) }
				</Button>
			</PanelBody>
		</PluginSidebar>
	);
}

domReady( () => {
	registerPlugin( 'post-to-convex-sidebar', {
		render: PostToConvexSidebar,
	} );
} );

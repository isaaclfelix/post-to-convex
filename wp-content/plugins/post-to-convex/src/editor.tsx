/**
 * Block editor script (enqueued only in the editor).
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, PanelBody } from '@wordpress/components';
import { useEntityId } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import domReady from '@wordpress/dom-ready';
import { PluginSidebar, store as editorStore } from '@wordpress/editor';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';

import {
	type CreatePostServerEndpointSchema,
	createPostServerEndpointSchema,
} from './schemas';

function PostToConvexSidebar() {
	const [ isPosting, setIsPosting ] = useState< boolean >( false );

	const postType = useSelect(
		( select ) => select( editorStore ).getCurrentPostType(),
		[]
	);

	const postId = useEntityId( 'postType', postType );

	/**
	 * Handle the post to Convex.
	 */
	const handlePost = useCallback( async () => {
		try {
			setIsPosting( true );

			const payload: CreatePostServerEndpointSchema = {
				id: Number( postId ),
			};

			const validatedPayload =
				createPostServerEndpointSchema.safeParse( payload );

			if ( ! validatedPayload.success ) {
				console.debug( validatedPayload.error );
				setIsPosting( false );
				return;
			}

			const response = await apiFetch( {
				path: '/post-to-convex/v1/createPostServer',
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify( validatedPayload.data ),
			} );

			console.debug( response );
		} catch ( error ) {
			console.debug( error );
		} finally {
			setIsPosting( false );
		}
	}, [ postId ] );

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

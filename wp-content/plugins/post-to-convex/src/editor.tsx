/**
 * Block editor script (enqueued only in the editor).
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, PanelBody } from '@wordpress/components';
import {
	store as coreStore,
	useEntityId,
	useEntityProp,
} from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import domReady from '@wordpress/dom-ready';
import { PluginSidebar, store as editorStore } from '@wordpress/editor';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';

import { POST_TO_CONVEX_REMOTE_ID_META_KEY, SCRIPT_DEBUG } from './constants';
import styles from './editor.module.css';
import {
	type CreatePostEndpointSchema,
	createPostEndpointSchema,
	type CreatePostResponseSchema,
	createPostResponseSchema,
	type RemovePostServerEndpointSchema,
	removePostServerEndpointSchema,
	type RemovePostServerResponseSchema,
	removePostServerResponseSchema,
	type UpdatePostEndpointSchema,
	updatePostEndpointSchema,
	type UpdatePostResponseSchema,
	updatePostResponseSchema,
} from './schemas';

function PostToConvexSidebar() {
	const [ isPutting, setIsPutting ] = useState< boolean >( false );
	const [ isPatching, setIsPatching ] = useState< boolean >( false );
	const [ isDeleting, setIsDeleting ] = useState< boolean >( false );
	const [ convexId, setConvexId ] = useState< string | null >( null );
	const [ success, setSuccess ] = useState< string >( '' );
	const [ error, setError ] = useState< string >( '' );

	const postType = useSelect( ( select ) => {
		const { getCurrentPostType } = select( editorStore ) as {
			getCurrentPostType: () => string;
		};

		return getCurrentPostType();
	}, [] );

	const postId = useEntityId( 'postType', postType );

	const postDirty = useSelect(
		( select ) =>
			select( coreStore ).hasEditsForEntityRecord(
				'postType',
				postType,
				postId
			),
		[ postType, postId ]
	);

	const [ meta ] = useEntityProp( 'postType', postType, 'meta' );

	const remoteIdFromMeta =
		typeof meta?.[ POST_TO_CONVEX_REMOTE_ID_META_KEY ] === 'string'
			? meta[ POST_TO_CONVEX_REMOTE_ID_META_KEY ]
			: '';

	useEffect( () => {
		if ( remoteIdFromMeta ) {
			setConvexId( remoteIdFromMeta );
		}
	}, [ remoteIdFromMeta ] );

	useEffect( () => {
		if ( postDirty ) {
			setSuccess( '' );
		}
	}, [ postDirty ] );

	/**
	 * Handle the post to Convex.
	 */
	const handlePut = useCallback( async () => {
		try {
			setSuccess( '' );
			setError( '' );
			setIsPutting( true );

			const payload: CreatePostEndpointSchema = {
				id: Number( postId ),
			};

			const validatedPayload =
				createPostEndpointSchema.safeParse( payload );

			if ( ! validatedPayload.success ) {
				if ( SCRIPT_DEBUG ) {
					// eslint-disable-next-line no-console
					console.debug( validatedPayload.error );
				}
				return;
			}

			const response: CreatePostResponseSchema = await apiFetch( {
				path: '/post-to-convex/v1/createPost',
				method: 'PUT',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify( validatedPayload.data ),
			} );

			if ( SCRIPT_DEBUG ) {
				// eslint-disable-next-line no-console
				console.debug( response );
			}

			const parsedResponse =
				createPostResponseSchema.safeParse( response );

			if ( ! parsedResponse.success ) {
				if ( SCRIPT_DEBUG ) {
					// eslint-disable-next-line no-console
					console.debug( parsedResponse.error );
				}
				return;
			}

			const {
				data: { id },
			} = parsedResponse.data;

			setConvexId( id );

			const successMessage = __( 'Post sent to Convex successfully.' );

			setSuccess( successMessage );
		} catch ( postError ) {
			if ( SCRIPT_DEBUG ) {
				// eslint-disable-next-line no-console
				console.debug( postError );
			}

			const errorMessage =
				postError instanceof Error
					? postError.message
					: __( 'Unknown error', 'post-to-convex' );

			setError( errorMessage );
		} finally {
			setIsPutting( false );
		}
	}, [ postId ] );

	/**
	 * Handle the update post to Convex.
	 */
	const handlePatch = useCallback( async () => {
		try {
			setSuccess( '' );
			setError( '' );
			setIsPatching( true );

			const payload: UpdatePostEndpointSchema = {
				id: Number( postId ),
			};

			const validatedPayload =
				updatePostEndpointSchema.safeParse( payload );

			if ( ! validatedPayload.success ) {
				if ( SCRIPT_DEBUG ) {
					// eslint-disable-next-line no-console
					console.debug( validatedPayload.error );
				}
				return;
			}

			const response: UpdatePostResponseSchema = await apiFetch( {
				path: '/post-to-convex/v1/updatePost',
				method: 'PATCH',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify( validatedPayload.data ),
			} );

			if ( SCRIPT_DEBUG ) {
				// eslint-disable-next-line no-console
				console.debug( response );
			}

			const parsedResponse =
				updatePostResponseSchema.safeParse( response );

			if ( ! parsedResponse.success ) {
				if ( SCRIPT_DEBUG ) {
					// eslint-disable-next-line no-console
					console.debug( parsedResponse.error );
				}
				return;
			}

			const successMessage = __(
				'Post updated in Convex successfully.',
				'post-to-convex'
			);

			setSuccess( successMessage );
		} catch ( patchError ) {
			if ( SCRIPT_DEBUG ) {
				// eslint-disable-next-line no-console
				console.debug( patchError );
			}

			const errorMessage =
				patchError instanceof Error
					? patchError.message
					: __( 'Unknown error', 'post-to-convex' );

			setError( errorMessage );
		} finally {
			setIsPatching( false );
		}
	}, [ postId ] );

	/**
	 * Handle the remove from Convex.
	 */
	const handleDelete = useCallback( async () => {
		try {
			setSuccess( '' );
			setError( '' );
			setIsDeleting( true );

			const payload: RemovePostServerEndpointSchema = {
				id: Number( postId ),
			};

			const validatedPayload =
				removePostServerEndpointSchema.safeParse( payload );

			if ( ! validatedPayload.success ) {
				if ( SCRIPT_DEBUG ) {
					// eslint-disable-next-line no-console
					console.debug( validatedPayload.error );
				}
				return;
			}

			const response: RemovePostServerResponseSchema = await apiFetch( {
				path: '/post-to-convex/v1/removePostServer',
				method: 'DELETE',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify( validatedPayload.data ),
			} );

			if ( SCRIPT_DEBUG ) {
				// eslint-disable-next-line no-console
				console.debug( response );
			}

			const parsedResponse =
				removePostServerResponseSchema.safeParse( response );

			if ( ! parsedResponse.success ) {
				if ( SCRIPT_DEBUG ) {
					// eslint-disable-next-line no-console
					console.debug( parsedResponse.error );
				}
				return;
			}

			setConvexId( null );

			setSuccess(
				__( 'Post removed from Convex successfully.', 'post-to-convex' )
			);
		} catch ( removeError ) {
			if ( SCRIPT_DEBUG ) {
				// eslint-disable-next-line no-console
				console.debug( removeError );
			}

			const errorMessage =
				removeError instanceof Error
					? removeError.message
					: __( 'Unknown error', 'post-to-convex' );

			setError( errorMessage );
		} finally {
			setIsDeleting( false );
		}
	}, [ postId ] );

	return (
		<PluginSidebar
			name="post-to-convex-sidebar"
			title={ __( 'Post to Convex' ) }
			icon={ 'smiley' }
		>
			<PanelBody>
				{ convexId ? (
					<>
						<Button
							variant="primary"
							onClick={ handlePatch }
							disabled={ isPatching || isDeleting || postDirty }
						>
							{ isPatching
								? __( 'Updating in Convex…' )
								: __( 'Update in Convex' ) }
						</Button>

						<Button
							variant="secondary"
							onClick={ handleDelete }
							disabled={ isPatching || isDeleting }
							className={
								styles[ 'post-to-convex-sidebar-button' ]
							}
						>
							{ isDeleting
								? __( 'Removing from Convex…' )
								: __( 'Remove from Convex' ) }
						</Button>
					</>
				) : (
					<Button
						variant="primary"
						onClick={ handlePut }
						disabled={ isPutting || postDirty }
					>
						{ isPutting
							? __( 'Posting to Convex…' )
							: __( 'Post to Convex' ) }
					</Button>
				) }

				{ convexId ? (
					<>
						<Notice
							status="info"
							isDismissible={ false }
							className={
								styles[ 'post-to-convex-sidebar-notice' ]
							}
						>
							{ __( 'Convex ID:' ) } { convexId }
						</Notice>
					</>
				) : null }

				{ postDirty ? (
					<Notice
						status="warning"
						isDismissible={ false }
						className={ styles[ 'post-to-convex-sidebar-notice' ] }
					>
						{ __(
							'Post has unsaved changes. Please save the post before posting to Convex.'
						) }
					</Notice>
				) : null }

				{ success ? (
					<Notice
						status="success"
						isDismissible={ false }
						className={ styles[ 'post-to-convex-sidebar-notice' ] }
					>
						{ success }
					</Notice>
				) : null }

				{ error ? (
					<Notice
						status="error"
						isDismissible={ false }
						className={ styles[ 'post-to-convex-sidebar-notice' ] }
					>
						{ error }
					</Notice>
				) : null }
			</PanelBody>
		</PluginSidebar>
	);
}

domReady( () => {
	registerPlugin( 'post-to-convex-sidebar', {
		render: PostToConvexSidebar,
	} );
} );

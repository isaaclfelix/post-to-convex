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

import { POST_TO_CONVEX_REMOTE_ID_META_KEY } from './constants';
import styles from './editor.module.css';
import {
	type CreateOrUpdatePostServerEndpointSchema,
	createOrUpdatePostServerEndpointSchema,
	type CreateOrUpdatePostServerResponseSchema,
	createOrUpdatePostServerResponseSchema,
	type RemovePostServerEndpointSchema,
	removePostServerEndpointSchema,
	type RemovePostServerResponseSchema,
	removePostServerResponseSchema,
} from './schemas';

function PostToConvexSidebar() {
	const [ isPosting, setIsPosting ] = useState< boolean >( false );
	const [ isRemoving, setIsRemoving ] = useState< boolean >( false );
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
	const handlePost = useCallback(
		async (
			_: React.MouseEvent< HTMLButtonElement >,
			isUpdate: boolean
		) => {
			try {
				setSuccess( '' );
				setError( '' );
				setIsPosting( true );

				const payload: CreateOrUpdatePostServerEndpointSchema = {
					id: Number( postId ),
					isUpdate,
				};

				const validatedPayload =
					createOrUpdatePostServerEndpointSchema.safeParse( payload );

				if ( ! validatedPayload.success ) {
					console.debug( validatedPayload.error );
					setIsPosting( false );
					return;
				}

				const response: CreateOrUpdatePostServerResponseSchema =
					await apiFetch( {
						path: '/post-to-convex/v1/createOrUpdatePostServer',
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify( validatedPayload.data ),
					} );

				console.debug( response );

				const parsedResponse =
					createOrUpdatePostServerResponseSchema.safeParse(
						response
					);

				if ( ! parsedResponse.success ) {
					console.debug( parsedResponse.error );
					return;
				}

				const {
					data: { id },
				} = parsedResponse.data;

				setConvexId( id );

				const successMessage = isUpdate
					? __( 'Post updated in Convex successfully.' )
					: __( 'Post sent to Convex successfully.' );

				setSuccess( successMessage );
			} catch ( postError ) {
				console.debug( postError );

				const errorMessage =
					postError instanceof Error
						? postError.message
						: __( 'Unknown error', 'post-to-convex' );

				setError( errorMessage );
			} finally {
				setIsPosting( false );
			}
		},
		[ postId ]
	);

	/**
	 * Handle the remove from Convex.
	 */
	const handleRemove = useCallback( async () => {
		try {
			setSuccess( '' );
			setError( '' );
			setIsRemoving( true );

			const payload: RemovePostServerEndpointSchema = {
				id: Number( postId ),
			};

			const validatedPayload =
				removePostServerEndpointSchema.safeParse( payload );

			if ( ! validatedPayload.success ) {
				console.debug( validatedPayload.error );
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

			console.debug( response );

			const parsedResponse =
				removePostServerResponseSchema.safeParse( response );

			if ( ! parsedResponse.success ) {
				console.debug( parsedResponse.error );
				return;
			}

			setConvexId( null );

			setSuccess(
				__( 'Post removed from Convex successfully.', 'post-to-convex' )
			);
		} catch ( removeError ) {
			console.debug( removeError );

			const errorMessage =
				removeError instanceof Error
					? removeError.message
					: __( 'Unknown error', 'post-to-convex' );

			setError( errorMessage );
		} finally {
			setIsRemoving( false );
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
							onClick={ (
								e: React.MouseEvent< HTMLButtonElement >
							) => handlePost( e, true ) }
							disabled={ isPosting || postDirty || isRemoving }
						>
							{ isPosting
								? __( 'Updating in Convex…' )
								: __( 'Update in Convex' ) }
						</Button>

						<Button
							variant="secondary"
							onClick={ handleRemove }
							disabled={ isPosting || isRemoving }
							className={
								styles[ 'post-to-convex-sidebar-button' ]
							}
						>
							{ isRemoving
								? __( 'Removing from Convex…' )
								: __( 'Remove from Convex' ) }
						</Button>
					</>
				) : (
					<Button
						variant="primary"
						onClick={ (
							e: React.MouseEvent< HTMLButtonElement >
						) => handlePost( e, false ) }
						disabled={ isPosting || postDirty }
					>
						{ isPosting
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

/**
 * Media Library and classic attachment edit UI for manual Convex sync.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import domReady from '@wordpress/dom-ready';
import {
	createRoot,
	useCallback,
	useEffect,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { POST_TO_CONVEX_MEDIA_ID_META_KEY } from './media-admin.constants';
import styles from './media-admin.module.css';
import {
	type RemoveAttachmentFromConvexEndpointSchema,
	removeAttachmentFromConvexEndpointSchema,
	type RemoveAttachmentFromConvexResponseSchema,
	removeAttachmentFromConvexResponseSchema,
	type SyncAttachmentEndpointSchema,
	syncAttachmentEndpointSchema,
	type SyncAttachmentResponseSchema,
	syncAttachmentResponseSchema,
} from './schemas';

type MediaAdminRoot = ReturnType< typeof createRoot >;

type MediaAttachmentMetaResponse = {
	meta?: Record< string, string >;
};

const mountedRoots = new WeakMap< HTMLElement, MediaAdminRoot >();
const mountedAttachmentIds = new WeakMap< HTMLElement, number >();

function collectMountElements( root: Node ): HTMLElement[] {
	const mounts: HTMLElement[] = [];

	if (
		root instanceof HTMLElement &&
		root.classList.contains( 'post-to-convex-media-mount' )
	) {
		mounts.push( root );
	}

	if ( root instanceof Element ) {
		root.querySelectorAll< HTMLElement >(
			'.post-to-convex-media-mount'
		).forEach( ( element ) => {
			mounts.push( element );
		} );
	}

	return mounts;
}

function unmountPanel( element: HTMLElement ): void {
	const root = mountedRoots.get( element );

	if ( root ) {
		root.unmount();
		mountedRoots.delete( element );
	}

	mountedAttachmentIds.delete( element );
}

async function fetchAttachmentConvexMediaId(
	attachmentId: number
): Promise< string > {
	const attachment = await apiFetch< MediaAttachmentMetaResponse >( {
		path: `/wp/v2/media/${ attachmentId }?context=edit&_fields=meta`,
	} );

	return attachment.meta?.[ POST_TO_CONVEX_MEDIA_ID_META_KEY ] ?? '';
}

function MediaConvexPanel( { attachmentId }: { attachmentId: number } ) {
	const [ mediaId, setMediaId ] = useState( '' );
	const [ isLoadingMediaId, setIsLoadingMediaId ] = useState( true );
	const [ isSending, setIsSending ] = useState( false );
	const [ isRemoving, setIsRemoving ] = useState( false );
	const [ success, setSuccess ] = useState( '' );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		let cancelled = false;

		const loadMediaId = async () => {
			setIsLoadingMediaId( true );

			try {
				const storedMediaId =
					await fetchAttachmentConvexMediaId( attachmentId );

				if ( ! cancelled ) {
					setMediaId( storedMediaId );
				}
			} catch {
				if ( ! cancelled ) {
					setMediaId( '' );
				}
			} finally {
				if ( ! cancelled ) {
					setIsLoadingMediaId( false );
				}
			}
		};

		loadMediaId();

		return () => {
			cancelled = true;
		};
	}, [ attachmentId ] );

	const handleSend = useCallback( async () => {
		try {
			setSuccess( '' );
			setError( '' );
			setIsSending( true );

			const payload: SyncAttachmentEndpointSchema = {
				id: attachmentId,
			};

			const validatedPayload =
				syncAttachmentEndpointSchema.safeParse( payload );

			if ( ! validatedPayload.success ) {
				return;
			}

			const response: SyncAttachmentResponseSchema = await apiFetch( {
				path: '/post-to-convex/v1/syncAttachment',
				method: 'PUT',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify( validatedPayload.data ),
			} );

			const parsedResponse =
				syncAttachmentResponseSchema.safeParse( response );

			if ( ! parsedResponse.success ) {
				return;
			}

			setMediaId( parsedResponse.data.data.mediaId );
			setSuccess(
				__(
					'Attachment sent to Convex successfully.',
					'post-to-convex'
				)
			);
		} catch ( sendError ) {
			const errorMessage =
				sendError instanceof Error
					? sendError.message
					: __( 'Unknown error', 'post-to-convex' );

			setError( errorMessage );
		} finally {
			setIsSending( false );
		}
	}, [ attachmentId ] );

	const handleRemove = useCallback( async () => {
		try {
			setSuccess( '' );
			setError( '' );
			setIsRemoving( true );

			const payload: RemoveAttachmentFromConvexEndpointSchema = {
				id: attachmentId,
			};

			const validatedPayload =
				removeAttachmentFromConvexEndpointSchema.safeParse( payload );

			if ( ! validatedPayload.success ) {
				return;
			}

			const response: RemoveAttachmentFromConvexResponseSchema =
				await apiFetch( {
					path: '/post-to-convex/v1/removeAttachmentFromConvex',
					method: 'DELETE',
					headers: {
						'Content-Type': 'application/json',
					},
					body: JSON.stringify( validatedPayload.data ),
				} );

			const parsedResponse =
				removeAttachmentFromConvexResponseSchema.safeParse( response );

			if ( ! parsedResponse.success ) {
				return;
			}

			setMediaId( '' );
			setSuccess(
				__(
					'Attachment removed from Convex successfully.',
					'post-to-convex'
				)
			);
		} catch ( removeError ) {
			const errorMessage =
				removeError instanceof Error
					? removeError.message
					: __( 'Unknown error', 'post-to-convex' );

			setError( errorMessage );
		} finally {
			setIsRemoving( false );
		}
	}, [ attachmentId ] );

	const hasMediaId = '' !== mediaId;

	if ( isLoadingMediaId ) {
		return (
			<div className="post-to-convex-media-panel">
				<Spinner />
			</div>
		);
	}

	return (
		<div className="post-to-convex-media-panel">
			{ hasMediaId ? (
				<Button
					variant="secondary"
					onClick={ handleRemove }
					disabled={ isSending || isRemoving }
					className={ styles[ 'post-to-convex-media-panel-button' ] }
				>
					{ isRemoving
						? __( 'Removing from Convex…', 'post-to-convex' )
						: __( 'Remove from Convex', 'post-to-convex' ) }
				</Button>
			) : (
				<Button
					variant="primary"
					onClick={ handleSend }
					disabled={ isSending || isRemoving }
					className={ styles[ 'post-to-convex-media-panel-button' ] }
				>
					{ isSending
						? __( 'Posting to Convex…', 'post-to-convex' )
						: __( 'Post to Convex', 'post-to-convex' ) }
				</Button>
			) }

			{ hasMediaId ? (
				<Notice
					status="info"
					isDismissible={ false }
					className={ styles[ 'post-to-convex-media-panel-notice' ] }
				>
					{ __( 'Convex ID:', 'post-to-convex' ) } { mediaId }
				</Notice>
			) : null }

			{ success ? (
				<Notice
					status="success"
					isDismissible={ false }
					className={ styles[ 'post-to-convex-media-panel-notice' ] }
				>
					{ success }
				</Notice>
			) : null }

			{ error ? (
				<Notice
					status="error"
					isDismissible={ false }
					className={ styles[ 'post-to-convex-media-panel-notice' ] }
				>
					{ error }
				</Notice>
			) : null }
		</div>
	);
}

function mountPanel( element: HTMLElement ): void {
	const attachmentId = parseInt(
		element.getAttribute( 'data-attachment-id' ) ?? '0',
		10
	);

	if ( ! attachmentId ) {
		return;
	}

	if ( mountedAttachmentIds.get( element ) === attachmentId ) {
		return;
	}

	mountedAttachmentIds.set( element, attachmentId );

	let root = mountedRoots.get( element );

	if ( root ) {
		root.unmount();
	}

	root = createRoot( element );
	mountedRoots.set( element, root );

	root.render(
		<MediaConvexPanel key={ attachmentId } attachmentId={ attachmentId } />
	);
}

function mountPanelsFromNodes( nodes: NodeList | Node[] ): void {
	const seen = new Set< HTMLElement >();

	for ( const node of nodes ) {
		for ( const element of collectMountElements( node ) ) {
			if ( seen.has( element ) || mountedRoots.has( element ) ) {
				continue;
			}

			seen.add( element );
			mountPanel( element );
		}
	}
}

function observePanelMounts(): void {
	const observer = new MutationObserver( ( mutations ) => {
		const addedNodes: Node[] = [];
		const removedMounts: HTMLElement[] = [];

		for ( const mutation of mutations ) {
			for ( const node of mutation.addedNodes ) {
				addedNodes.push( node );
			}

			for ( const node of mutation.removedNodes ) {
				for ( const element of collectMountElements( node ) ) {
					removedMounts.push( element );
				}
			}
		}

		for ( const element of removedMounts ) {
			unmountPanel( element );
		}

		if ( addedNodes.length > 0 ) {
			mountPanelsFromNodes( addedNodes );
		}
	} );

	observer.observe( document.body, {
		childList: true,
		subtree: true,
	} );
}

domReady( () => {
	mountPanelsFromNodes(
		document.querySelectorAll( '.post-to-convex-media-mount' )
	);
	observePanelMounts();
} );

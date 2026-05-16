declare global {
	interface Window {
		postToConvexEditor: {
			remoteIdMetaKey: string;
		};
	}
}

export const POST_TO_CONVEX_REMOTE_ID_META_KEY =
	window.postToConvexEditor.remoteIdMetaKey;

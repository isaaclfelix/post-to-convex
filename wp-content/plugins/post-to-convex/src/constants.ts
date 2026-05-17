declare global {
	interface Window {
		postToConvexEditor: {
			remoteIdMetaKey: string;
			scriptDebug: boolean;
		};
	}
}

export const POST_TO_CONVEX_REMOTE_ID_META_KEY =
	window.postToConvexEditor.remoteIdMetaKey;

export const SCRIPT_DEBUG = window.postToConvexEditor.scriptDebug;

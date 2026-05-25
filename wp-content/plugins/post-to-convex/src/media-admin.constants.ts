declare global {
	interface Window {
		postToConvexMediaAdmin: {
			mediaIdMetaKey: string;
			scriptDebug: boolean;
		};
	}
}

export const POST_TO_CONVEX_MEDIA_ID_META_KEY =
	window.postToConvexMediaAdmin.mediaIdMetaKey;

export const SCRIPT_DEBUG = window.postToConvexMediaAdmin.scriptDebug;

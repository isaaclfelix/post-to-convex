import z from 'zod';

/**
 * Schemas for the create post endpoint.
 */
export const createPostEndpointSchema = z.strictObject( {
	id: z.number(),
} );

export type CreatePostEndpointSchema = z.infer<
	typeof createPostEndpointSchema
>;

export const createPostResponseSchema = z.strictObject( {
	message: z.string(),
	data: z.object( {
		id: z.string(),
	} ),
} );

export type CreatePostResponseSchema = z.infer<
	typeof createPostResponseSchema
>;

/**
 * Schemas for the update post endpoint.
 */
export const updatePostEndpointSchema = z.strictObject( {
	id: z.number(),
} );

export type UpdatePostEndpointSchema = z.infer<
	typeof updatePostEndpointSchema
>;

export const updatePostResponseSchema = z.strictObject( {
	message: z.string(),
	data: z.object( {
		id: z.string(),
	} ),
} );

export type UpdatePostResponseSchema = z.infer<
	typeof updatePostResponseSchema
>;

/**
 * Schemas for the remove post endpoint.
 */
export const removePostServerEndpointSchema = z.strictObject( {
	id: z.number(),
} );

export type RemovePostServerEndpointSchema = z.infer<
	typeof removePostServerEndpointSchema
>;

export const removePostServerResponseSchema = z.strictObject( {
	message: z.string(),
	data: z.object( {
		id: z.string(),
	} ),
} );

export type RemovePostServerResponseSchema = z.infer<
	typeof removePostServerResponseSchema
>;

/**
 * Schemas for manual attachment sync from the Media Library UI.
 */
export const syncAttachmentEndpointSchema = z.strictObject( {
	id: z.number(),
} );

export type SyncAttachmentEndpointSchema = z.infer<
	typeof syncAttachmentEndpointSchema
>;

export const syncAttachmentResponseSchema = z.strictObject( {
	message: z.string(),
	data: z.object( {
		mediaId: z.string(),
	} ),
} );

export type SyncAttachmentResponseSchema = z.infer<
	typeof syncAttachmentResponseSchema
>;

export const removeAttachmentFromConvexEndpointSchema = z.strictObject( {
	id: z.number(),
} );

export type RemoveAttachmentFromConvexEndpointSchema = z.infer<
	typeof removeAttachmentFromConvexEndpointSchema
>;

export const removeAttachmentFromConvexResponseSchema = z.strictObject( {
	message: z.string(),
} );

export type RemoveAttachmentFromConvexResponseSchema = z.infer<
	typeof removeAttachmentFromConvexResponseSchema
>;

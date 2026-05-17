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

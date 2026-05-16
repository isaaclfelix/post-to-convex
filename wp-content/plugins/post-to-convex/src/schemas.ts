import z from 'zod';

export const createPostEndpointSchema = z.strictObject( {
	title: z.string(),
	slug: z.string(),
	content: z.string(),
	excerpt: z.string(),
	type: z.string(),
	status: z.string(),
	commentStatus: z.string(),
	createdAt: z.string(),
	updatedAt: z.string(),
	originalId: z.number(),
	authorId: z.number(),
	categoryIds: z.array( z.number() ),
	tagIds: z.array( z.number() ),
} );

export type CreatePostEndpointSchema = z.infer<
	typeof createPostEndpointSchema
>;

export const createOrUpdatePostServerEndpointSchema = z.strictObject( {
	id: z.number(),
	isUpdate: z.boolean(),
} );

export type CreateOrUpdatePostServerEndpointSchema = z.infer<
	typeof createOrUpdatePostServerEndpointSchema
>;

export const createOrUpdatePostServerResponseSchema = z.strictObject( {
	message: z.string(),
	data: z.object( {
		id: z.string(),
	} ),
} );

export type CreateOrUpdatePostServerResponseSchema = z.infer<
	typeof createOrUpdatePostServerResponseSchema
>;

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

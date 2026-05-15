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

export const createPostServerEndpointSchema = z.strictObject( {
	id: z.number(),
} );

export type CreatePostServerEndpointSchema = z.infer<
	typeof createPostServerEndpointSchema
>;

import z from 'zod';

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

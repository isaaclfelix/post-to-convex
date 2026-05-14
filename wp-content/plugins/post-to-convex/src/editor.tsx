/**
 * Block editor script (enqueued only in the editor).
 */
import { Button, PanelBody } from '@wordpress/components';
import domReady from '@wordpress/dom-ready';
import { PluginSidebar } from '@wordpress/editor';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';

function PostToConvexSidebar() {
	const [ isPosting, setIsPosting ] = useState( false );

	return (
		<PluginSidebar
			name="post-to-convex-sidebar"
			title={ __( 'Post to Convex' ) }
			icon={ 'smiley' }
		>
			<PanelBody>
				<Button variant="primary">{ __( 'Post to Convex' ) }</Button>
			</PanelBody>
		</PluginSidebar>
	);
}

domReady( () => {
	registerPlugin( 'post-to-convex-sidebar', {
		render: PostToConvexSidebar,
	} );
} );

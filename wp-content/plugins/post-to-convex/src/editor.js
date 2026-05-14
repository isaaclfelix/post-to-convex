/**
 * Block editor script (enqueued only in the editor).
 */
import domReady from '@wordpress/dom-ready';
import { PluginSidebar } from '@wordpress/editor';
import {
	PanelBody,
	Button,
} from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';
import { __ } from '@wordpress/i18n';

function PostToConvexSidebar() {
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
	)
}

domReady( () => {
	registerPlugin('post-to-convex-sidebar', {
		render: PostToConvexSidebar,
	})
} );

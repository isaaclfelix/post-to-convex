<?php
/**
 * Tests for the theme.json preset resolver.
 *
 * @package Post_To_Convex
 */

declare( strict_types=1 );

namespace PostToConvex\Tests;

use PostToConvex\BlockHandlers\PresetResolver;
use WP_Theme_JSON_Data;
use WP_Theme_JSON_Resolver;
use WP_UnitTestCase;

/**
 * Drives the resolver with a deterministic theme.json payload so resolution
 * does not depend on whichever theme the test environment happens to have
 * activated. Slugs that are not in the palette resolve to null.
 */
class PresetResolverTest extends WP_UnitTestCase {

	/**
	 * Resolver under test.
	 *
	 * @var PresetResolver
	 */
	private PresetResolver $resolver;

	/**
	 * Install the deterministic theme.json filter and clear WordPress'
	 * resolver cache so the next call to wp_get_global_settings() picks up
	 * the test palette.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		add_filter( 'wp_theme_json_data_theme', array( $this, 'inject_test_theme_json' ) );
		WP_Theme_JSON_Resolver::clean_cached_data();
		$this->resolver = new PresetResolver();
	}

	/**
	 * Restore filters and re-clear the cache so other tests start from a
	 * known state.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'wp_theme_json_data_theme', array( $this, 'inject_test_theme_json' ) );
		WP_Theme_JSON_Resolver::clean_cached_data();
		parent::tear_down();
	}

	/**
	 * Inject a deterministic palette for tests.
	 *
	 * Uses slugs that intentionally do not collide with WordPress core
	 * default presets so the merged palette will only contain our entries
	 * for these slugs. (Slugs that match a core default — e.g.
	 * `pale-cyan-blue` — get concatenated into the merged list and the
	 * resolver behaviour for collisions is exercised in the heading-handler
	 * suite via a fake resolver.)
	 *
	 * @param WP_Theme_JSON_Data $theme_json Theme JSON object provided by core.
	 * @return WP_Theme_JSON_Data Updated theme JSON object.
	 */
	public function inject_test_theme_json( WP_Theme_JSON_Data $theme_json ): WP_Theme_JSON_Data {
		return $theme_json->update_with(
			array(
				'version'  => 2,
				'settings' => array(
					'color'      => array(
						'palette' => array(
							array(
								'slug'  => 'ptc-test-red',
								'color' => '#cf2e2e',
							),
							array(
								'slug'  => 'ptc-test-blue',
								'color' => '#abb8c3',
							),
							array(
								'slug'  => 'ptc-test-white',
								'color' => '#ffffff',
							),
						),
					),
					'typography' => array(
						'fontSizes' => array(
							array(
								'slug' => 'ptc-test-small',
								'size' => '13px',
							),
							array(
								'slug' => 'ptc-test-xlarge',
								'size' => '42px',
							),
						),
					),
					'spacing'    => array(
						'spacingSizes' => array(
							array(
								'slug' => 'ptc-test-50',
								'size' => '1.25rem',
							),
						),
					),
				),
			)
		);
	}

	/**
	 * A color slug present in the palette resolves to its hex value.
	 *
	 * @return void
	 */
	public function test_resolve_color_returns_hex_for_known_slug(): void {
		$this->assertSame( '#cf2e2e', $this->resolver->resolve_color( 'ptc-test-red' ) );
		$this->assertSame( '#abb8c3', $this->resolver->resolve_color( 'ptc-test-blue' ) );
		$this->assertSame( '#ffffff', $this->resolver->resolve_color( 'ptc-test-white' ) );
	}

	/**
	 * A color slug missing from the palette resolves to null.
	 *
	 * @return void
	 */
	public function test_resolve_color_returns_null_for_unknown_slug(): void {
		$this->assertNull( $this->resolver->resolve_color( 'ptc-not-in-palette' ) );
		$this->assertNull( $this->resolver->resolve_color( '' ) );
	}

	/**
	 * Font-size presets resolve through the typography palette.
	 *
	 * @return void
	 */
	public function test_resolve_font_size(): void {
		$this->assertSame( '13px', $this->resolver->resolve_font_size( 'ptc-test-small' ) );
		$this->assertSame( '42px', $this->resolver->resolve_font_size( 'ptc-test-xlarge' ) );
		$this->assertNull( $this->resolver->resolve_font_size( 'ptc-no-such-size' ) );
	}

	/**
	 * Spacing presets resolve through the spacing palette.
	 *
	 * @return void
	 */
	public function test_resolve_spacing(): void {
		$this->assertSame( '1.25rem', $this->resolver->resolve_spacing( 'ptc-test-50' ) );
		$this->assertNull( $this->resolver->resolve_spacing( 'ptc-test-999' ) );
	}

	/**
	 * `var:preset|<kind>|<slug>` tokens parse into the slug.
	 *
	 * @return void
	 */
	public function test_extract_preset_slug_from_pipe_token(): void {
		$this->assertSame(
			'vivid-red',
			$this->resolver->extract_preset_slug( 'var:preset|color|vivid-red', 'color' )
		);
		$this->assertSame(
			'50',
			$this->resolver->extract_preset_slug( 'var:preset|spacing|50', 'spacing' )
		);
	}

	/**
	 * `var( --wp--preset--<kind>--<slug> )` CSS expressions parse into the slug.
	 *
	 * @return void
	 */
	public function test_extract_preset_slug_from_css_var(): void {
		$this->assertSame(
			'vivid-red',
			$this->resolver->extract_preset_slug( 'var(--wp--preset--color--vivid-red)', 'color' )
		);
		$this->assertSame(
			'50',
			$this->resolver->extract_preset_slug( 'var( --wp--preset--spacing--50 )', 'spacing' )
		);
	}

	/**
	 * Literal CSS values (e.g. `7px`, `#fff`) are not preset references and
	 * return null from the slug extractor — the caller can then pass them
	 * through as the literal `resolved` value.
	 *
	 * @return void
	 */
	public function test_extract_preset_slug_returns_null_for_literals(): void {
		$this->assertNull( $this->resolver->extract_preset_slug( '7px', 'spacing' ) );
		$this->assertNull( $this->resolver->extract_preset_slug( '#ffffff', 'color' ) );
		$this->assertNull( $this->resolver->extract_preset_slug( '2.4', 'spacing' ) );
	}

	/**
	 * The kind argument scopes the match — a color token is not pulled out
	 * when looking for a spacing slug, and vice versa.
	 *
	 * @return void
	 */
	public function test_extract_preset_slug_is_kind_scoped(): void {
		$this->assertNull(
			$this->resolver->extract_preset_slug( 'var:preset|color|vivid-red', 'spacing' )
		);
		$this->assertNull(
			$this->resolver->extract_preset_slug( 'var:preset|spacing|50', 'color' )
		);
	}
}

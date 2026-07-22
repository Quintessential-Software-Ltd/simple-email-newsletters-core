/**
 * Admin enhancements. Intentionally tiny — the plugin works without JS.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		// Live character hint for the preheader field.
		var pre = document.getElementById( 'semnews-preheader' );
		if ( pre ) {
			var hint = document.createElement( 'span' );
			hint.className = 'description';
			hint.style.marginLeft = '8px';
			var update = function () {
				hint.textContent = pre.value.length + ' / 120';
			};
			update();
			pre.addEventListener( 'input', update );
			if ( pre.parentNode ) {
				pre.parentNode.appendChild( hint );
			}
		}

		// Campaign editor: warn before unsaved changes are lost. The side-panel
		// buttons (Send now, Schedule, Send test, Duplicate) each act on the
		// LAST SAVED draft and reload the page, so un-saved edits would be sent
		// stale AND silently discarded. Same for plain navigation away.
		var saveAction = document.querySelector( 'form input[name="action"][value="semnews_save_campaign"]' );
		var mainForm   = saveAction ? saveAction.form : null;
		if ( mainForm && window.semnewsEditorL10n ) {
			var formDirty = false;
			var allowNav  = false;

			var isDirty = function () {
				if ( formDirty ) {
					return true;
				}
				// The visual editor lives in an iframe, so its edits don't
				// bubble to the form; TinyMCE tracks them itself.
				return !! ( window.tinymce && window.tinymce.get( 'semnews-body' ) && window.tinymce.get( 'semnews-body' ).isDirty() );
			};

			mainForm.addEventListener( 'input', function () { formDirty = true; } );
			mainForm.addEventListener( 'change', function () { formDirty = true; } );

			// Intercept the stale-acting side buttons first (capture phase),
			// before their own inline confirms run.
			var guarded = [ 'semnews_send_campaign', 'semnews_schedule_campaign', 'semnews_send_test', 'semnews_duplicate_campaign' ];
			document.addEventListener( 'submit', function ( e ) {
				var action = e.target && e.target.querySelector ? e.target.querySelector( 'input[name="action"]' ) : null;
				if ( action && guarded.indexOf( action.value ) !== -1 && isDirty() && ! window.confirm( semnewsEditorL10n.unsaved ) ) {
					e.preventDefault();
					e.stopPropagation();
				}
			}, true );

			// Once any form really submits, don't let the browser's own
			// unsaved-changes prompt double-ask during the navigation.
			document.addEventListener( 'submit', function ( e ) {
				if ( ! e.defaultPrevented ) {
					allowNav = true;
				}
			}, false );

			// "Preview in browser" opens a new tab, so nothing is lost — but it
			// shows the saved version, which surprises people mid-edit.
			document.querySelectorAll( 'a[href*="semnews_preview_campaign"]' ).forEach( function ( link ) {
				link.addEventListener( 'click', function ( e ) {
					if ( isDirty() && ! window.confirm( semnewsEditorL10n.preview ) ) {
						e.preventDefault();
					}
				} );
			} );

			window.addEventListener( 'beforeunload', function ( e ) {
				if ( isDirty() && ! allowNav ) {
					e.preventDefault();
					e.returnValue = '';
				}
			} );
		}

		// Automation screen: show only the schedule fields that apply to the
		// chosen frequency, and the custom-HTML field only for that template.
		var freq = document.getElementById( 'semnews-frequency' );
		if ( freq ) {
			var sync = function () {
				document.querySelectorAll( '[data-freq]' ).forEach( function ( el ) {
					el.style.display = ( el.getAttribute( 'data-freq' ) === freq.value ) ? '' : 'none';
				} );
			};
			sync();
			freq.addEventListener( 'change', sync );
		}

		var tpl = document.getElementById( 'semnews-template' );
		if ( tpl ) {
			var rowFor = function ( el ) {
				return el.closest( 'tr' ) || el;
			};
			var syncTpl = function () {
				document.querySelectorAll( '[data-template-only]' ).forEach( function ( el ) {
					rowFor( el ).style.display = ( el.getAttribute( 'data-template-only' ) === tpl.value ) ? '' : 'none';
				} );
			};
			syncTpl();
			tpl.addEventListener( 'change', syncTpl );
		}

		// Campaign editor: the template Preview button follows the selection.
		var buildTpl = document.getElementById( 'semnews-build-template' );
		var tplPreview = document.getElementById( 'semnews-template-preview' );
		if ( buildTpl && tplPreview ) {
			var syncPreview = function () {
				tplPreview.href = tplPreview.getAttribute( 'data-base' ) + '&template=' + encodeURIComponent( buildTpl.value );
			};
			syncPreview();
			buildTpl.addEventListener( 'change', syncPreview );
		}
	} );
}() );

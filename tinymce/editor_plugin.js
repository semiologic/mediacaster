(function() {
	// Load plugin specific language pack
	// tinymce.PluginManager.requireLangPack('mediacaster');

	tinymce.create('tinymce.plugins.mediacaster', {
		/**
		 * Initializes the plugin, this will be executed after the plugin has been created.
		 * This call is done before the editor instance has finished it's initialization so use the onInit event
		 * of the editor instance to intercept that event.
		 *
		 * @param {tinymce.Editor} ed Editor instance that the plugin is initialized in.
		 * @param {string} url Absolute URL to where the plugin is located.
		 */
		init : function(ed, url) {
			var mediacasterAudioHTML = '<img src="' + url + '/images/trans.gif" alt="$1" class="mceMediacaster mceMediacasterAudio mceItemNoResize" title="$1" />';
			var mediacasterVideoHTML = '<img src="' + url + '/images/trans.gif" alt="$1" class="mceMediacaster mceMediacasterVideo mceItemNoResize" title="$1" />';
			var mediacasterAttachmentHTML = '<img src="' + url + '/images/trans.gif" alt="$1" class="mceMediacaster mceMediacasterAttachment mceItemNoResize" title="$1" />';

			// Load plugin specific CSS into editor
			ed.onInit.add(function() {
				ed.dom.loadCSS(url + '/css/content.css');
			});

			// Display mediacaster file instead if img in element path
			ed.onPostRender.add(function() {
				if (ed.theme.onResolveName) {
					ed.theme.onResolveName.add(function(th, o) {
						if (o.node.nodeName == 'IMG' && ed.dom.hasClass(o.node, 'mceMediacaster')) {
							var file = ed.dom.getAttrib(o.node, 'alt');
							o.name = file;
						}
					});
				}
			});

			// Replace mediacaster files with images
			ed.onBeforeSetContent.add(function(ed, o) {
				o.content = o.content.replace(/\[(?:audio|video):(.*?)\]/ig, '[media:$1]');
				o.content = o.content.replace(/<--media#(.*?)-->/ig, '[media:$1]');
				o.content = o.content.replace(/\[media:(https?:\/\/[^\/\]]*(?:youtube|google).+?)\]/ig, mediacasterVideoHTML);
				o.content = o.content.replace(/\[media:(.*?\.(?:swf|flv|mp4|m4v|m4a|mov))\]/ig, mediacasterVideoHTML);
				o.content = o.content.replace(/\[media:(.*?\.(?:zip|gz|pdf))\]/ig, mediacasterAttachmentHTML);
				o.content = o.content.replace(/\[media:(.*?)\]/ig, mediacasterAudioHTML);
			});

			// Replace images with mediacaster files
			ed.onPostProcess.add(function(ed, o) {
				if (o.get)
					o.content = o.content.replace(/<img[^>]+>/g, function(im) {
						if (im.indexOf('class="mceMediacaster') !== -1) {
                            var m = im.match(/alt="(.*?)"/i);
							var file = m[1];

                            im = '[media:' + file + ']' + "\n\n";
                        }
						
                        return im;
					});
			});
		},
		
		
		/**
		 * Creates control instances based in the incomming name. This method is normally not
		 * needed since the addButton method of the tinymce.Editor class is a more easy way of adding buttons
		 * but you sometimes need to create more complex controls like listboxes, split buttons etc then this
		 * method can be used to create those.
		 *
		 * @param {String} n Name of the control to create.
		 * @param {tinymce.ControlManager} cm Control manager to use inorder to create new control.
		 * @return {tinymce.ui.Control} New control instance or null if no control was created.
		 */
		createControl : function(n, cm) {
			switch ( n )
			{
			case 'mediacaster':
				var myMediacasterDropdown = cm.createListBox('MediacasterDropdown', {
					title : 'Mediacaster',
					onselect : function(v) {
						if ( v == 'media:url' )
						{
							v = prompt('Enter the url of a media file:', 'http://');
							
							if ( v == 'http://' )
							{
								v = '';
							}
						}
						
						if ( v )
						{
							v = '[media:' + v + ']';
						
							window.tinyMCE.execInstanceCommand('content', 'mceInsertContent', false, v);
							window.tinyMCE.execCommand("mceCleanup");
						}
						
						tinyMCE.activeEditor.controlManager.get('MediacasterDropdown').reset();
					}
				});

				// Default value
				myMediacasterDropdown.add('Enter a url', 'media:url');
				
				// Media files
				if ( document.mediacasterFiles )
				{
					var i;
					for ( i = 0; i < document.mediacasterFiles.length; i++ )
					{
						myMediacasterDropdown.add(document.mediacasterFiles[i].label, document.mediacasterFiles[i].value);
					}
				}

				// Return the new listbox instance
				return myMediacasterDropdown;
			}
			
			return null;
		},
		

		/**
		 * Returns information about the plugin as a name/value array.
		 * The current keys are longname, author, authorurl, infourl and version.
		 *
		 * @return {Object} Name/value array containing information about the plugin.
		 */
		getInfo : function() {
			return {
				longname : "Mediacaster",
				author : 'Denis de Bernardy',
				authorurl : 'http://www.semiologic.com/',
				infourl : 'http://www.semiologic.com/software/mediacaster/',
				version : "1.5"
			};
		}
	});

	// Register plugin
	tinymce.PluginManager.add('mediacaster', tinymce.plugins.mediacaster);
})();
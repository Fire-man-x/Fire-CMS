$(function () {
	//init modals - own method
	$.nette.ext('modals', {
		init: function () {
			//add method to "snippets"
			this.ext('snippets', true).before($.proxy(function ($el) { // $el je element snippetu
				if (this.shouldTry && $el.parents('.modal').length === 1) {
					$el.parents('.modal').modal('show');
					this.shouldTry = false;
				}
			}, this));
		},
		success: function (payload) {
			this.shouldTry = true;
			//bind again
			//dissable show modal on click - .mesour-ajax, must be .click, .on method is dissabled in init
			$("a.mesour-ajax").click(function(){
				initModals.shouldTry = false;
			});
		}
	}, {
		//initialize
		shouldTry: true
	});

	//init cursor - own method
	$.nette.ext('cursor', {
		before: function () {
			$("body").addClass("wait");
		},
		complete: function () {
			$("body").removeClass("wait");
		}
	}, {
		//initialize
		shouldTry: true
	});

	//show bootstrap confirm - disable ajax before show modal
	$.nette.ext('confirm', {
		init: function() {
			//store href to different attribute
			$('[data-target="#confirm-modal"]').each(function(){
				$(this).data('confirm-url', $(this).attr('href'));
				$(this).attr('href', '#');
			});
		},
		load: function (rh) {
			//remove click event, added - if added class .ajax to element and run reload
			$('#confirm-modal a#confirm-button').off('click.nette', rh);
			$('#confirm-modal a.ajax').on('click.nette', rh);
			$('#confirm-modal a.ajax').off('click.confirm').on('click.confirm', function() {
				$('#confirm-modal').modal('hide');
			});
		}
	});

	//Init nette ajax - must be last
	$.nette.init();

	//confirm modal
	$('#confirm-modal').on('show.bs.modal', function(e) {
		//restore href from different attribute
		let confirmButton = $(this).find('#confirm-button');
		confirmButton.attr('href', $(e.relatedTarget).data('confirm-url'));
		confirmButton.html($(e.relatedTarget).html());
		//class
		if($(e.relatedTarget).hasClass('ajax')) {
			confirmButton.addClass('ajax');
		} else {
			confirmButton.removeClass('ajax');
		}

		//title
		$('#confirm-modalLabel').text($(e.relatedTarget).data('confirm-text'));

		//reload ajax
		$.nette.load();
	})
	.on('shown.bs.modal', function(e) {
		$(this).find('#confirm-button').trigger('focus');
	});

	//offcanvas menu
	$('[data-toggle=offcanvas]').click(function () {
		$('.row-offcanvas').toggleClass('active');
	});

	//sidebar icons
	$("#sidebar").on("mouseenter", ".nav-item", function(){
		$(this).find(".buttons").addClass("show");
	}).on("mouseleave", ".nav-item", function(){
		$(this).find(".buttons").removeClass("show");
	});

	//hide alerts
	/*window.setTimeout(function() {
		$(".alert").alert('close');
	}, 2500);*/

	//url
	var ajaxTimeout;
	$(".validate-url").on("change keyup", function(){
		if(ajaxTimeout){
			clearTimeout(ajaxTimeout);
		}
		var textValue = $(this).val()
		ajaxTimeout = setTimeout(function(){
			$.ajax({
				url: "",
				data: {
					"do": "validateUrl",
					"text": textValue
				},
				success: function(payload){
					$(".validate-url-output").val(payload.url);
				}
			});
		}, 500);
	});

	//category-type
	$("#frm-categoryForm-type").on("change",function(){
		window.location = $(this).find(":selected").data("link");
	});

	//change iframe src
	$(document).on('click', "[data-src][data-target]", function() {
		var target = $(this).data('target');
		var src = $(this).attr('data-src');
		var height = $(this).attr('data-height') || $(window).height() - 113; /* - is outerHeight + margin; Math.abs($(".modal-header").outerHeight())*/;
		var width = $(this).attr('data-width') || "100%";

		$(target + " iframe").attr({'src': src})
			.css({'width': width, 'height': height, 'border': 0});
	});

	//select image from file manager
	$("#content-inside").on('change', ".galery-thumbnail input", function(e) {
		if($(this).is(":checked")){
			$(this).parent().addClass("selected");
		}else{
			$(this).parent().removeClass("selected");
		}
		/*$(this).toggleClass("selected");

		e.stopPropagation();
		e.preventDefault();*/
	});

	//find selected files
	var findSelectedFiles = function (only_fileId) {
		return $(".images-selection .selected").map(function () {
			if (only_fileId) {
				return $(this).data("file-id");
			} else {
				return {
					"file_id": $(this).data("file-id"),
					"is_image": $(this).data("is-image")
				};
			}
		}).get();
	};

	//bulk editing - files manager
	$("select[id='files-manager_order-by']").on("change", function(){
		//funguje zatim jen na link
		var selectedOption = $(this).find("option:selected");
		$.nette.ajax({
			url: selectedOption.data("link"),
			spinner: true,
			data: {
				"files": findSelectedFiles(true)
			}
		});
	});

	//bulk editing - files manager
	$("select[name='files-manager_bulk-edit']").on("change", function(){
		var selectedOption = $(this).find("option:selected");
		if ($(this).val() === "delete" && selectedOption.data("link") !== "") {
			$.nette.ajax({
				url: selectedOption.data("link"),
				spinner: true,
				data: {
					"files": findSelectedFiles(true)
				}
			});
		} else if ($(this).val() === "selectAll") { //mark all
			$(".galery-thumbnail input[type='checkbox']:not(:checked)").click();
		} else if ($(this).val() === "deselectAll") { //unmark all
			$(".galery-thumbnail input[type='checkbox']:checked").click();
		}
	});

	//trigger event in iframe
	$("#use-selected-files").on("click", function (){
		// Helper function to get parameters from the query string.
		function getUrlParam(paramName)
		{
			var reParam = new RegExp('(?:[\?&]|&amp;)' + paramName + '=([^&]+)', 'i');
			var match = window.location.search.match(reParam);

			return (match && match.length > 1) ? match[1] : '';
		}
		var fromWysiwyg = getUrlParam('fromWysiwyg');
		var toLanguage = getUrlParam('toLanguage');

		var selectedItems = findSelectedFiles();
		parent.$('body').trigger( "selected-images-from-iframe", [selectedItems, fromWysiwyg, toLanguage]);
	});

	//catch event from iframe
	var selectedFilesCallback = null;
	var selectedFilesFromIframeUrl = window.selectedFilesFromIframeUrl || null;
	// odkaz, který otevírá správce souborů, může nést vlastní URL pro vybrané soubory (např. obrázky jedné
	// položky menu v gridu) - má přednost před globálním window.selectedFilesFromIframeUrl
	$(document).on('click', "[data-selected-files-url]", function() {
		selectedFilesFromIframeUrl = $(this).attr('data-selected-files-url');
	});
	$("body").on("selected-images-from-iframe", function(e, items, isFromWysiwyg, toLanguage){
		if (selectedFilesFromIframeUrl === null) {
			console.error('URL for "selected files" is not defined');
			//throw "URL is not defined";
		} else {
			if(!isFromWysiwyg && items.length > 0){
				$.nette.ajax({
					url: selectedFilesFromIframeUrl,
					spinner: true,
					data: {
						"files": items.map(function (item) {
							return item["file_id"];
						}),
						"language": toLanguage
					},
					success: function (payload) {
						$(".validate-url-output").val(payload.url);
					}
				});
			} else if (selectedFilesCallback !== null) {
				items.forEach(function (item) {
					if (item.is_image) {
						selectedFilesCallback("[image, " + item.file_id + ", 300x300]");
					} else {
						selectedFilesCallback("[file, " + item.file_id + "]");
					}
				});
			}
		}

		//hide modal
		$('#modal').modal('hide');

		// Helper function to get parameters from the query string.
		/*function getUrlParam(paramName)
		{
			var reParam = new RegExp('(?:[\?&]|&amp;)' + paramName + '=([^&]+)', 'i');
			var match = window.location.search.match(reParam);

			return (match && match.length > 1) ? match[1] : '';
		}
		var funcNum = getUrlParam('CKEditorFuncNum');
		if (funcNum !== null && funcNum !== ""){
			var fileUrl = items;
			window.opener.CKEDITOR.tools.callFunction(funcNum, fileUrl);
		}*/

	});


	//filemanager - moving files to other folder
	var fileManagerMovingFiles = function(){
		//draggable
		$(".galery-thumbnail").draggable({
			revert: "invalid",
			cursor: "grabbing",
			zIndex: 200,
			cursorAt: {top: 0, left: -15},
			drag: function( event, ui ) {
				if(!$(this).hasClass("selected")){
					//mark
					$(this).find("input[type='checkbox']").click();
					//update helper
					$("#drag-helper").text("("+findSelectedFiles(true).length+")");
				}
			},
			helper: function (event) {
				return $("<div class='ui-widget-header' id='drag-helper'>("+findSelectedFiles(true).length+")</div>");
			}
		});

		//droppable
		var changeFolderOfFilesUrl = window.changeFolderOfFilesUrl || null;
		$(".file-item-droppable").droppable({
			drop: function (event, ui) {
				var splitted = $(this).data("id").split("_");

				if (changeFolderOfFilesUrl === null) {
					//throw "URL is not defined";
				} else {
					$.nette.ajax({
						url: changeFolderOfFilesUrl,
						spinner: true,
						data: {
							"toFolder": splitted[1],
							"files": findSelectedFiles(true)
						},
						complete: function () {
							fileManagerMovingFiles();
						}
					});
				}

			}
		});
	};
	fileManagerMovingFiles();


	//enable popover
	$('.popover').popover({ });
	$('[data-toggle="popover"]').popover({
		html: true
	});
	//enable tooltip
	$('[data-toggle="tooltip"]').tooltip();



	//Tokenfield
	$('input[data-tagInput]').each(function () {
		var settings = jQuery.parseJSON($(this).attr('data-tagInput'));

		var data = new Bloodhound({
			datumTokenizer: Bloodhound.tokenizers.obj.whitespace(settings.labelPropertyName),
			queryTokenizer: Bloodhound.tokenizers.whitespace,
			remote: {
				url: settings.url,
				wildcard: 'QUERY'
			}
		});

		data.initialize();

		$(this).tokenfield({
			typeahead: [null,{
				name: 'data',
				displayKey: settings.labelPropertyName,
				source: data.ttAdapter()
			}]
		});
	});



	//default setting for all autocomplete render function
	$.ui.autocomplete.prototype._renderItem = function(ul, item) {
		return $("<li>")
		.data('item.autocomplete', item)
		.append("<a>" + item.long_name + "</a>")
		.appendTo(ul);
	};

	//autocomplete town
	if($(".autocomplete.autocomplete-town").length){
		$(".autocomplete.autocomplete-town").autocomplete({
			source: "/calendar/ajax/town/",
			open: function(event, ui) {
              $(this).parent().addClass("selected");
            },
			close: function(event, ui) {
              $(this).parent().removeClass("selected");
            },
			focus: function( event, ui ) {
				$(".selected .autocomplete-town").val( ui.item.name );
				$(".selected .autocomplete-district").val( ui.item.district_id );
				return false;
			},
			select: function( event, ui ) {
				$(".selected .autocomplete-town").val( ui.item.name );
				$(".selected .autocomplete-district").val( ui.item.district_id );
				return false;
			}
		})
		.on("focus keyup", function() {
			if($(this).val()==""){
				$(this).autocomplete('search', '%');
			}
		});
	}


	//autocomplete place
	if($(".autocomplete.autocomplete-place").length){
		$(".autocomplete.autocomplete-place").autocomplete({
			source: function( request, response ) {
				$.ajax({
					url: "/calendar/ajax/place/",
					data: {
						town: $(".autocomplete.autocomplete-town").val(),
						term: request.term
					},
					success: function( data ) {
						response(data);
					}
				});
			},
			focus: function( event, ui ) {
				$( ".autocomplete-place" ).val( ui.item.name );
				$( ".autocomplete-gps_location" ).val( ui.item.gps_location );
				return false;
			},
			select: function( event, ui ) {
				$( ".autocomplete-place" ).val( ui.item.name );
				$( ".autocomplete-gps_location" ).val( ui.item.gps_location );
				return false;
			}
		})
		.on("focus keyup", function() {
			if($(this).val()==""){
				$(this).autocomplete('search', '%');
			}
		})
		.data("ui-autocomplete")._renderItem = function( ul, item ) {
			return $( "<li></li>" )
			.data( "item.autocomplete", item )
			.append( "<a>" + item.name + "</a>" )
			.appendTo( ul );
		};
	}


	//autocomplete member-place
	if($(".autocomplete.autocomplete-member-name").length){
		$(".autocomplete.autocomplete-member-name").autocomplete({
			source: function( request, response ) {
				$.ajax({
					url: "/calendar/ajax/member-name/",
					data: {
						term: request.term
					},
					success: function( data ) {
						response(data);
					}
				});
			},
			focus: function( event, ui ) {
				$(".autocomplete-member-name").val( ui.item.member_name );
				$(".autocomplete-town").val( ui.item.town_name );
				$(".autocomplete-district").val( ui.item.district_id );
				$("input[name='team_name']").val(ui.item.team_name);
				return false;
			},
			select: function( event, ui ) {
				$(".autocomplete-member-name").val( ui.item.member_name );
				$(".autocomplete-town").val( ui.item.town_name );
				$(".autocomplete-district").val( ui.item.district_id );
				$("input[name='team_name']").val(ui.item.team_name);
				return false;
			}
		})
		.data("ui-autocomplete")._renderItem = function( ul, item ) {
			return $( "<li></li>" )
			.data( "item.autocomplete", item )
			.append('<div>'+item.member_name+' ('+item.town_name+(item.team_name != null ? ' ['+item.team_name+']' : '')+ ') <small>'+item.frequency+'x</small></div>')
			.appendTo( ul );
		};
	}


	//CK Editor
	var linkToFileManagerUrl = window.linkToFileManagerUrl || null;
	var nameFileManager = window.nameFileManager || "Files manager";
	/*$("textarea.summernote").each(function(){
		CKEDITOR.replace( $(this).attr("name"),{
			language: 'cs',
			//entities_greek: false,
			entities_latin: false,
			filebrowserBrowseUrl: linkToFileManagerUrl,//'/browser/browse.php?type=Images',
			//filebrowserUploadUrl: '/uploader/upload.php?type=Files'
		} );
	});
	//CK Editor in modal
	$.fn.modal.Constructor.prototype.enforceFocus = function () {
    var $modalElement = this.$element;
    $(document).on('focusin.modal', function (e) {
        var $parent = $(e.target.parentNode);
        if ($modalElement[0] !== e.target && !$modalElement.has(e.target).length
            // add whatever conditions you need here:
            &&
            !$parent.hasClass('cke_dialog_ui_input_select') && !$parent.hasClass('cke_dialog_ui_input_text')) {
            $modalElement.focus()
        }
    })
	};*/

	//Summernote
	(function (factory) {
		/* global define */
		if (typeof define === 'function' && define.amd) {
			// AMD. Register as an anonymous module.
			define(['jquery'], factory);
		} else if (typeof module === 'object' && module.exports) {
			// Node/CommonJS
			module.exports = factory(require('jquery'));
		} else {
			// Browser globals
			factory(window.jQuery);
		}
	}(function ($) {

		// Extends plugins for adding readmore.
		//  - plugin is external module for customizing.
		$.extend($.summernote.plugins, {
			/**
			 * @param {Object} context - context object has status of editor.
			 * /
			'readmore': function (context) {
				var self = this;

				// ui has renders to build ui elements.
				//  - you can create a button with `ui.button`
				var ui = $.summernote.ui;

				// add readmore button
				context.memo('button.readmore', function () {
					// create button
					var button = ui.button({
						contents: '<i class="fa fa-long-arrow-right"/> Read-More',
						tooltip: 'Read More',
						click: function () {

							context.invoke('editor.insertText', '[[--readmore--]]');
						}
					});

					// create jQuery object from button instance.
					var $readmore = button.render();
					return $readmore;
				});


				// This methods will be called when editor is destroyed by $('..').summernote('destroy');
				// You should remove elements on `initialize`.
				this.destroy = function () {
					this.$panel.remove();
					this.$panel = null;
				};
			},*/
			'elfinder': function (context) {
				var self = this;

				// ui has renders to build ui elements.
				//  - you can create a button with `ui.button`
				var ui = $.summernote.ui;

				// add elfinder button
				context.memo('button.elfinder', function () {
					// create button
					var button = ui.button({
						contents: '<i class="fa fa-list-alt"/> '+nameFileManager,
						tooltip: nameFileManager,
						click: function () {
							var target = "#modal";
							var src = linkToFileManagerUrl+"&fromWysiwyg=1";
							var height = $(this).attr('data-height') || $(window).height() - 113; /* - is outerHeight + margin; Math.abs($(".modal-header").outerHeight())*/;
							var width = $(this).attr('data-width') || "100%";

							$(target + " iframe")
								.attr({'src': src})
								.css({'width': width, 'height': height, 'border': 0});

							$(target).modal('show');
							context.invoke('editor.insertText', "");

							selectedFilesCallback = function(text){
								context.invoke('editor.insertText', text);
							}
						}
					});

					// create jQuery object from button instance.
					var $elfinder = button.render();
					return $elfinder;
				});

				// This methods will be called when editor is destroyed by $('..').summernote('destroy');
				// You should remove elements on `initialize`.
				this.destroy = function () {
					this.$panel.remove();
					this.$panel = null;
				};
			},
			/*'mymodal': function(context) {
				var self = this;

				var ui = $.summernote.ui;

				context.memo('button.mymodal', function() {
					var button = ui.button({
						contents: '<i class="fa fa-child"/> my modal',
						tooltip: 'my modal',
						click: function() {
							// call bootstrap method
							self.$mymodal.modal('show');
						}
					});

					// create jQuery object from button instance.
					var $hello = button.render();
					return $hello;
				});

				this.initialize = function() {
					// append your modal basic html here
					// like:
					this.$mymodal = $('body').append(
						'<div class="modal fade">\
							... inner html ...\
						</div>'
						);
				};

				this.destroy = function() {
					// remove your modal basic html here
					this.$mymodal.remove();
				};
			},
			'minidiag': function (context) {
				var self = this;

				// ui has renders to build ui elements.
				//  - you can create a button with `ui.button`
				var ui = $.summernote.ui;

				var $editor = context.layoutInfo.editor;
				var options = context.options;

				// add context menu button
				context.memo('button.minidiag', function () {
					return ui.button({
						contents: '<i class="fa fa-file-o"/>',
						tooltip: 'mini dialog',
						click: context.createInvokeHandler('minidiag.showDialog')
					}).render();
				});

				// This method will be called when editor is initialized by $('..').summernote();
				// You can create elements for plugin
				self.initialize = function () {
					var $container = options.dialogsInBody ? $(document.body) : $editor;

					var body = '<div class="form-group row-fluid">' +
							'<h2>minimal dialog body</h2>' +
							'</div>';
					var footer = '<button href="#" class="btn btn-primary ext-minidiag-btn">OK</button>';

					self.$dialog = ui.dialog({
						title: 'minimal dialog title',
						fade: options.dialogsFade,
						body: body,
						footer: footer
					}).render().appendTo($container);

				};

				// This methods will be called when editor is destroyed by $('..').summernote('destroy');
				// You should remove elements on `initialize`.
				self.destroy = function () {
					self.$dialog.remove();
					self.$dialog = null;
				};

				self.showDialog = function () {
					self
							.openDialog()
							.then(function (dialogData) {
								// [workaround] hide dialog before restore range for IE range focus
								ui.hideDialog(self.$dialog);
								context.invoke('editor.restoreRange');

								// do something with dialogData
								console.log("dialog returned: ", dialogData)
								// ...
							})
							.fail(function () {
								context.invoke('editor.restoreRange');
							});

				};

				self.openDialog = function () {
					return $.Deferred(function (deferred) {
						var $dialogBtn = self.$dialog.find('.ext-minidiag-btn');

						ui.onDialogShown(self.$dialog, function () {
							context.triggerEvent('dialog.shown');

							$dialogBtn
									.click(function (event) {
										event.preventDefault();

										deferred.resolve({action: 'mini dialog OK clicked...'});
									});
						});

						ui.onDialogHidden(self.$dialog, function () {
							$dialogBtn.off('click');

							if (deferred.state() === 'pending') {
								deferred.reject();
							}
						});

						ui.showDialog(self.$dialog);
					});
				};

			},*/
			/*'myPopover': function (context) {
				var self = this;
				var ui = $.summernote.ui;

				var options = context.options;
				var lang = options.langInfo;

				context.memo('button.myPopoverLinkDialogShow', function () {
				  return ui.button({
					contents: ui.icon(options.icons.link),
					tooltip: lang.link.edit,
					click: context.createInvokeHandler('linkDialog.show')
				  }).render();
				});

				context.memo('button.myPopoverUnlink', function () {
				  return ui.button({
					contents: ui.icon(options.icons.unlink),
					tooltip: lang.link.unlink,
					click: context.createInvokeHandler('editor.unlink')
				  }).render();
				});

				this.events = {
					'summernote.keyup summernote.mouseup summernote.change summernote.scroll': function () {
						self.update();
					},
					'summernote.dialog.shown': function () {
						self.hide();
					}
				};

				this.shouldInitialize = function () {
					return true;
					//return !list.isEmpty(options.popover.link);
				};

				this.initialize = function () {
					this.$popover = ui.popover({
						className: 'note-shortcode-file-popover',
						callback: function ($node) {
							var $content = $node.find('.popover-content');
							$content.prepend('<span><input name="shortcode-file-size" class="form-control-inline input-sm text" id="form-shortcode-file-size" size="6" type="text">&nbsp;</span>');
						}
					}).render().appendTo('body');

					var $content = this.$popover.find('.popover-content');

					context.invoke('buttons.build', $content, options.popover.link);
				};

				this.destroy = function () {
					this.$popover.remove();
				};

				this.update = function () {
					console.log("myPopovowe update");

					// Prevent focusing on editable when invoke('code') is executed
					if (!context.invoke('editor.hasFocus')) {
						this.hide();
						return;
					}
					var dom = $.summernote.dom;

					var rng = context.invoke('editor.createRange');
					var spanTag = dom.ancestor(rng.sc, dom.isSpan);
					var jQSpanTag = $(spanTag);
					if (rng.isCollapsed() && spanTag && jQSpanTag.data("file-id")) {
						var text = jQSpanTag.text();
						var correctFormat = text.match(/\[file,\s*(.*?)\]/);
						if(correctFormat){
							var attributes = correctFormat[1];

							//remove all &quot;
							attributes =attributes.replace(/&quot;/, "\"");
							//remove all whitespaces, out of quots
							attributes = attributes.replace(/(&nbsp;|\s)+(?=([^"]*"[^"]*")*[^"]*$)/, "");

							//split parameters to array
							var itemParams = attributes.split(",");

							var fileId = itemParams[0];

							$(itemParams).each(function(index, item){
								if(index === 0){
									return;
								}
								var separatedValues = item.split("=>");
								var name = separatedValues[0];
								var value = separatedValues[1];
								window.console.log(name);
								window.console.log(value);

							});



							/*var name = $(spanTag).attr('href');
							var showSize = $(spanTag).attr('href');
							this.$popover.find('a').attr('href', href).html(href);*/

							/*var pos = dom.posFromPlaceholder(spanTag);
							this.$popover.css({
								display: 'block',
								left: pos.left,
								top: pos.top
							});
						} else {
							this.hide();
						}

					} else {
						this.hide();
					}
				};

				this.hide = function () {
					this.$popover.hide();
				};
			}*//*,
			'myImagePopover': function (context) {
				var self = this;
				var ui = $.summernote.ui;

				var options = context.options;

				this.events = {
					'summernote.keyup summernote.mouseup summernote.change summernote.scroll': function () {
						self.update();
					},
					'summernote.dialog.shown': function () {
						self.hide();
					}
				};

				this.shouldInitialize = function () {
					return true;
					//return !list.isEmpty(options.popover.image);
				};

				this.initialize = function () {
					this.$popover = ui.popover({
						className: 'note-image-popover',
						callback: function ($node) {
							var $content = $node.find('.popover-content');
							$content.prepend('<span><input name="shortcode-file-size" class="form-control-inline input-sm text" id="form-shortcode-file-size" value="obrazek" size="6" type="text">&nbsp;</span>');
						}
					}).render().appendTo('body');
					var $content = this.$popover.find('.popover-content');

					context.invoke('buttons.build', $content, options.popover.image);
				};

				this.destroy = function () {
					this.$popover.remove();
				};

				this.update = function (target) {
					console.log("myImagePopover update");
					console.log(target);
					var dom = $.summernote.dom;
					if (dom.isImg(target)) {
						var pos = dom.posFromPlaceholder(target);
						this.$popover.css({
							display: 'block',
							left: pos.left,
							top: pos.top + 50
						});
					} else {
						this.hide();
					}
				};

				this.hide = function () {
					this.$popover.hide();
				};
			}*/

		});
	}));
	$('textarea.summernote').summernote({
		lang: 'cs-CZ', // default: 'en-US'
		maxHeight: 1000,

		//airMode: true,

		toolbar: [
			['style', ['style']],
			['style', ['bold', 'italic', 'underline', 'strikethrough', 'superscript', 'subscript', 'clear']],
			['fontname', ['fontname']],
			['fontsize', ['fontsize']],
			['color', ['color']],
			['para', ['ul', 'ol', 'paragraph']],
			['height', ['height']],
			['table', ['table']],
			['insert', ['link', 'picture', 'video', 'hr', 'readmore']],
			['cms', ['elfinder', 'mymodal', 'minidiag', 'myPopover']],
			['view', ['fullscreen', 'codeview']]/*,
			['help', ['help']]*/
		],
		popover: {
			image: [
				['imagesize', ['imageSize100', 'imageSize50', 'imageSize25']],
				['float', ['floatLeft', 'floatRight', 'floatNone']],
				['remove', ['removeMedia']]
			],
			link: [
				//['link', ['linkDialogShow', 'unlink']]
				['link', ['linkDialogShow']]
			],
			myPopover: [
				['myPopover', ['myPopoverLinkDialogShow', 'unlink']]
			],
			air: [
				['color', ['color']],
				['font', ['bold', 'underline', 'clear']],
				['para', ['ul', 'paragraph']],
				['table', ['table']],
				['insert', ['link', 'picture']]
			]
		}
		/*toolbar: [
			['style', ['bold', 'italic', 'underline', 'clear']],
			['insert', ['link', 'picture', 'video', 'hr', 'elfinder']],
			['view', ['fullscreen', 'codeview']],
		]/*,
		onImageUpload: function (files, editor, welEditable) {
			sendFile(files[0], editor, welEditable);
		}*/
		/*callbacks: {
			onChange: function (contents, $editable) {
				console.log('onChange:', contents, $editable);
			}
		}*/
	});
	$('.note-popover').css({'display': 'none'});
	$('.note-editor .note-icon-caret').remove();
});

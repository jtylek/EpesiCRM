/**
 * @license Copyright (c) 2003-2014, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or http://ckeditor.com/license
 */

CKEDITOR.editorConfig = function( config ) {
	// Define changes to default configuration here. For example:
	// config.language = 'fr';
	// config.uiColor = '#AADC6E';
    config.removeButtons = "Save";
    config.extraPlugins = 'toolbarswitch';
    config.allowedContent = true;
    config.toolbar_Basic = [['Bold', 'Italic', '-', 'NumberedList', 'BulletedList', '-', 'Link', 'Unlink', '-', 'Source', '-', 'About'], ['Toolbarswitch']];
    config.smallToolbar = 'Basic';
    config.maximizedToolbar = 'Full';
};

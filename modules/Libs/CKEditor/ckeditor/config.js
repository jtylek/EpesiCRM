/**
 * @license Copyright (c) 2003-2019, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

CKEDITOR.editorConfig = function( config ) {
	// Define changes to default configuration here. For example:
	// config.language = 'fr';
	// config.uiColor = '#AADC6E';
	config.removeButtons = "Save";
    config.extraPlugins = ['toolbarswitch', 'tableresize', 'lineheight'];
    config.allowedContent = true;
    config.toolbar_Basic = [['Bold', 'Italic', '-', 'NumberedList', 'BulletedList', '-', 'Link', 'Unlink', '-', 'Source', '-', 'About'], ['Toolbarswitch']];
    config.smallToolbar = 'Basic';
    config.maximizedToolbar = 'Full';
    config.tabSpaces = 4;
};

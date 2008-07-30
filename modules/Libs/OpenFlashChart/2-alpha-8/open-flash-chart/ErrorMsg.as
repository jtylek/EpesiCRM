/* */

package {
	
	import flash.display.Sprite;
	import flash.display.Stage;
    import flash.text.TextField;
    import flash.text.TextFieldType;
	import flash.text.TextFormat;
	import flash.events.Event;
	import flash.text.TextFieldAutoSize;
	import string.Css;
	
	
	public class ErrorMsg extends Sprite {
		
		public function ErrorMsg( msg:String ):void {
			
			var title:TextField = new TextField();
			title.text = msg;
			
			var fmt:TextFormat = new TextFormat();
			fmt.color = 0x000000;
			fmt.font = "Courier";
			fmt.size = 10;
			fmt.align = "left";
		
			title.setTextFormat(fmt);
			title.autoSize = "left";
			title.border = true;
			title.x = 5;
			title.y = 5;
			
			this.addChild(title);
		}
	}
}
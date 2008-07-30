package {
	import flash.display.Sprite;
	import flash.text.TextField;
	import flash.text.TextFormat;
	import flash.display.DisplayObject;
	import string.Utils;
	import com.serialization.json.JSON;
	
	public class XAxisLabels extends Sprite {
		
		public var need_labels:Boolean;
		public var labels:Array;
		// JSON style:
		private var style:Object;
		
		//
		// Ugh, ugly code so we can rotate the text:
		//
		[Embed(systemFont='Arial', fontName='spArial', mimeType='application/x-font')]
		public static var ArialFont:Class;

		function XAxisLabels( json:Object ) {
			
			this.need_labels = true;
			
			var style:XLabelStyle = new XLabelStyle( json.x_labels );
			
			
			this.style = {
				rotate:		null,
				visible:	true,
				labels:		[],
				steps:		1,
				size:		10,
				colour:		'#000000'
			};
			
			// cache the text for tooltips
			this.labels = new Array();
			
			if( ( json.x_axis != null ) && ( json.x_axis.labels != null ) )
				object_helper.merge_2( json.x_axis.labels, this.style.labels );
			
			this.style.colour = Utils.get_colour( this.style.colour );
			
			if( ( this.style.labels is Array ) && ( this.style.labels.length > 0 ) )
			{
				//
				// we WERE passed labels
				//
				this.need_labels = false;
				
				for each( var s:Object in this.style.labels )
					this.add( s, this.style );
			}
		}
		
		//
		// we were not passed labels and need to make
		// them from the X Axis range
		//
		public function auto_label( range:Range ):void {
			
			//
			// if the user has passed labels we don't do this
			//
			if( this.need_labels )
				if( this.style.visible )
					for( var i:Number = range.min; i < range.max; i++ )
						this.add( NumberUtils.formatNumber( i ), this.style );
		}
		
		public function add( label:Object, style:Object ) : void
		{
			var label_style:Object = {
				colour:		style.colour,
				text:		'',
				rotate:		style.rotate,
				size:		style.size,
				colour:		style.colour
				//,visible:    true		// style.visible
			};

			
			//
			// inherit some properties from
			// our parents 'globals'
			//
			if( label is String )
				label_style.text = label as String;
			else {
				object_helper.merge_2( label, label_style );
			}
			
			// our parent colour is a number, but
			// we may have our own colour:
			if( label_style.colour is String )
				label_style.colour = Utils.get_colour( label_style.colour );
			
			// tr.ace( JSON.serialize( label_style ) );
			
			this.labels.push( label_style.text );

			//
			// inheriting the 'visible' attribute
			// is complext due to the 'steps' value
			// only some labels will be visible
			//
			if( label_style.visible == null )
			{
				//
				// some labels will be invisible due to our parents step value
				//
				if ( ( (this.labels.length - 1) % style.steps ) == 0 )
					label_style.visible = true;
				else
					label_style.visible = false;
			}
			
			var l:TextField = this.make_label( label_style );
			this.addChild( l );
		}
		
		public function get( i:Number ) : String
		{
			if( i<this.labels.length )
				return this.labels[i];
			else
				return '';
		}
	
		
		public function make_label( label_style:Object ):TextField {
			// we create the text in its own movie clip, so when
			// we rotate it, we can move the regestration point
			
			var title:TextField = new TextField();
            title.x = 0;
			title.y = 0;
			
			//this.css.parseCSS(this.style);
			//title.styleSheet = this.css;
			title.text = label_style.text;
			
			var fmt:TextFormat = new TextFormat();
			fmt.color = label_style.colour;
		
			if( label_style.rotate is String )
			{
				// so we can rotate the text
				fmt.font = "spArial";
				title.embedFonts = true;
			}
			else
			{
				fmt.font = "Verdana";
			}

			
			fmt.size = label_style.size;
			fmt.align = "left";
			title.setTextFormat(fmt);
			title.autoSize = "left";
			
			if( label_style.rotate == 'vertical' )
			{
				title.rotation = 270;
			}
			else if( label_style.rotate == 'diagonal' )
			{
				title.rotation = -45;
			}
			else
			{
				title.x = -(title.width/2);
			}
			// we don't know the x & y locations yet...
			
			title.visible = label_style.visible;
			
			return title;
		}
		
		public function count() : Number
		{
			return this.labels.length;
		}
		
		public function get_height() : Number
		{
			var height:Number = 0;
			for( var pos:Number=0; pos < this.numChildren; pos++ )
			{
				var child:DisplayObject = this.getChildAt(pos);
				height = Math.max( height, child.height );
			}
			
			return height;
		}
		
		public function resize( sc:ScreenCoords, yPos:Number ) : void//, b:Box )
		{
			var i:Number = 0;
			
			for( var pos:Number=0; pos < this.numChildren; pos++ )
			{
				var child:DisplayObject = this.getChildAt(pos);
				child.x = sc.get_x_tick_pos(pos) - (child.width / 2);
				child.y = yPos;
				
				if( this.style.rotate == 'vertical' )
					child.y += child.height;
				
				if( this.style.rotate == 'diag' )
					child.y += child.height;

//				i+=this.style.step;
			}
		}
		
		//
		// to help Box calculate the correct width:
		//
		public function last_label_width() : Number
		{
			// is the last label shown?
//			if( ( (this.labels.length-1) % style.step ) != 0 )
//				return 0;
				
			// get the width of the right most label
			// because it may stick out past the end of the graph
			// and we don't want to truncate it.
//			return this.mcs[(this.mcs.length-1)]._width;
			if ( this.numChildren > 0 )
				return this.getChildAt(this.numChildren - 1).width;
			else
				return 0;
		}
		
		// see above comments
		public function first_label_width() : Number
		{
			if( this.numChildren>0 )
				return this.getChildAt(0).width;
			else
				return 0;
		}
	}
}
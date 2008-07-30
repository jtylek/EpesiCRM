package ChartObjects {
	import com.serialization.json.JSON;
	
	public class Factory
	{
		private var attach_right:Array;

		public static function MakeChart( json:Object ) : ObjectCollection
		{
			var collection:ObjectCollection = new ObjectCollection();
			
			// multiple bar charts all have the same X values, so
			// they are grouped around each X value, this tells
			// ScreenCoords how to group them:
			var bar_group:Number = 0;
			var name:String = '';
			var c:Number=1;
			
			var elements:Array = json['elements'] as Array;
			
			for( var i:Number = 0; i < elements.length; i++ )
			{
				tr.ace( elements[i]['type'] );
				
				switch( elements[i]['type'] ) {
					case 'bar' :
						collection.add( new Bar( elements[i], bar_group ) );
						bar_group++;
						break;
					
					case 'line':
						collection.add( new Line( elements[i] ) );
						break;
						
					case 'line_dot':
						collection.add( new LineDot( elements[i] ) );
						break;
					
					case 'line_hollow':
						collection.add( new LineHollow( elements[i] ) );
						break;
						
					case 'area_hollow':
						collection.add( new AreaHollow( elements[i] ) );
						break;
						
					case 'pie':
						collection.add( new Pie( elements[i] ) );
						break;
						
					case 'hbar':
						collection.add( new HBar( elements[i] ) );
						bar_group++;
						break;
						
					case 'bar_stack':
						collection.add( new BarStack( elements[i], c, bar_group ) );
						bar_group++;
						break;
						
					case 'scatter':
						collection.add( new Scatter( elements[i] ) );
						break;
						
					case 'bar_sketch':
						collection.add( new BarSketch( elements[i], bar_group ) );
						bar_group++;
						break;
						
					case 'bar_glass':
						collection.add( new BarGlass( elements[i], bar_group ) );
						bar_group++;
						break;
					
					case 'bar_fade':
						collection.add( new BarFade( elements[i], bar_group ) );
						bar_group++;
						break;
					
					case 'bar_3d':
						collection.add( new Bar3D( elements[i], bar_group ) );
						bar_group++;
						break;
					
					case 'bar_filled':
						collection.add( new BarOutline( elements[i], bar_group ) );
						bar_group++;
						break;
		
				}
			}
			/*
					
			
				else if ( lv['candle' + name] != undefined )
				{
					ob = new BarCandle(lv, c, bar_group);
					bar_group++;
				}
				else if ( lv['bar_sketch' + name] != undefined )
				{
					ob = new BarSketch(lv, c, bar_group);
					bar_group++;
				}
				else if ( lv['bar_stack' + name] != undefined )
				{
					ob = new BarStack(lv, c, bar_group);
					bar_group++;
				}
				else if ( lv['hbar' + name] != undefined )
				{
					ob = new HBar( lv, c, bar_group );
					bar_group++;
				}
				
					//
					// BUG: These need to be fixed at some point:
					//
//					if( lv['candle'+name] != undefined )
//						ob.set_values( lv['values'+name], x_axis_labels, lv['links'+name] );
//					else if( lv['hlc'+name] != undefined )
//						ob.set_values( lv['values'+name], x_axis_labels, lv['links'+name] );

				if( ob )
					collection.add(ob);
					
				c++;
			}
			while( false );
			*/
		
			var y2:Boolean = false;
			var y2lines:Array;
			
			//
			// some data sets are attached to the right
			// Y axis (and min max)
			//
//			this.attach_right = new Array();
				
//			if( lv.show_y2 != undefined )
//				if( lv.show_y2 != 'false' )
//					if( lv.y2_lines != undefined )
//					{
//						this.attach_right = lv.y2_lines.split(",");
//					}
			
			collection.groups = bar_group;
			return collection;
		}
		
		
		
		private static function makeObject( lv:Array, name:String, c:Number ):Base {
			if( lv['line'+name] != undefined )
				return new Line(lv);
//			else if( lv['line_dot'+name] != undefined )
//				return new LineDot(lv,name);
//			else if( lv['line_hollow'+name] != undefined )
//				return new LineHollow(lv,name);
/*			else if( lv['area_hollow'+name] != undefined )
				return new AreaHollow(lv,name);
*/
			else if ( lv['bar' + name] != undefined )
				return new Bar( lv, 1);// Factory.bar_group );
/*
			else if( lv['filled_bar'+name] != undefined )
				return new FilledBarStyle(lv,name);
			else if( lv['bar_glass'+name] != undefined )
				return new BarGlassStyle(lv,name);
			else if( lv['bar_fade'+name] != undefined )
				return new BarFade(lv,name);
			else if( lv['bar_zebra'+name] != undefined )
				return new BarZebra(lv['bar_zebra'+name],'bar_'+c);
			else if( lv['bar_arrow'+name] != undefined )
				return new BarArrow(lv,name);
			else if( lv['bar_3d'+name] != undefined )
				return new Bar3D(lv,name);
			else if( lv['pie'+name] != undefined )
				return new PieStyle(lv,name);
			else if( lv['candle'+name] != undefined )
				return new CandleStyle(lv,name);
			else if( lv['scatter'+name] != undefined )
				return new Scatter(lv,name);
			else if( lv['hlc'+name] != undefined )
				return new HLCStyle(lv,name);
			else if( lv['bar_sketch'+name] != undefined )
				return new BarSketchStyle(lv,name);
	*/
			return null;
		}
	}
}
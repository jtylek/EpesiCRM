package {
	import org.flashdevelop.utils.FlashConnect;
	import com.serialization.json.JSON;
	
	public class tr {
		
		public static function ace( o:Object ):void	{
			if ( o == null )
				FlashConnect.trace( 'null' );
			else
				FlashConnect.trace( o.toString() );
		}
		
		public static function ace_json( json:Object ):void {
			tr.ace(JSON.serialize(json));
		}
	}
}
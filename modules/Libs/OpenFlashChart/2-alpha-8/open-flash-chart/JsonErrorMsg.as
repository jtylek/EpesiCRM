package {
	
	public class JsonErrorMsg extends ErrorMsg {
		
		public function JsonErrorMsg( json:String, e:Error ):void {
			
			var tmp:String = "Open Flash Chart\n\n";
			tmp += "JSON Parse Error ["+ e.message +"]\n";
			
			var pos:Number = json.indexOf( "\n", e.errorID );
			var s:String = json.substr(0, pos);
			var lines:Array = s.split("\n");
			
			tmp += "Error at character " + e.errorID + ", line " + lines.length +":\n\n";
			tmp += (lines.length-3).toString() +": "+ lines[lines.length-3];
			tmp += (lines.length-2).toString() +": "+ lines[lines.length-2];
			tmp += (lines.length-1).toString() +": "+ lines[lines.length-1];
			
			super( tmp );
		}
	}
}
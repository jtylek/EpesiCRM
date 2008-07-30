<?php

class area_hollow
{
	function area_hollow()
	{
		$this->type      = "area_hollow";
		
		$tmp = 'fill-alpha';
		$this->$tmp = 0.35;
		
		$this->values    = array();
	}
	
	function set_width( $w )
	{
		$this->width     = $w;
	}
	
	function set_colour( $colour )
	{
		$this->colour = $colour;
	}
	
	function set_values( $v )
	{
		$this->values = $v;		
	}
	
	function set_dot_size( $size )
	{
		$tmp = 'dot-size';
		$this->$tmp = $size;
	}
	
	function set_key( $text, $font_size )
	{
		$this->text      = $text;
		$tmp = 'font-size';
		$this->$tmp = $font_size;
	}
}

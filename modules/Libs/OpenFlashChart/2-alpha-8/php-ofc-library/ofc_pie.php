<?php

class pie_value
{
	function pie_value( $value, $text )
	{
		$this->value = $value;
		$this->text = $text;
	}
}

class pie
{
	function pie()
	{
		$this->type      		= 'pie';
		$this->colours     		= array("#d01f3c","#356aa0","#C79810");
		$this->alpha			= 0.6;
		$this->border			= 2;
	}
	
	function set_values( $v )
	{
		$this->values = $v;		
	}
	
	// boolean
	function set_animate( $animate )
	{
		$this->animate = $animate;
	}
	
	// real
	function set_start_angle( $angle )
	{
		$tmp = 'start-angle';
		$this->$tmp = $angle;
	}
	
	function set_tooltip( $tip )
	{
		$this->tip = $tip;
	}
}

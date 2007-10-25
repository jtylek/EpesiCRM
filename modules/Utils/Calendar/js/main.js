Utils_Calendar = function(link_proto, instance_id) {
		this.monthName = new Array('January','February','March','April','May','June','July','August','September','October','November','December');
		this.link_proto = link_proto;
		this.instance_id = instance_id;
		// show a month
		this.show_month = function( month, year ) {
			var days = new Array('Mon','Tue','Wed','Thu','Fri','Sat','Sun');
			var daysInMonth = new Array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
			var daysInWeek = 7;
			
			// formatting constants
			var TRstart = '<tr>';
			var TRend = '</tr>';
			var TDstartHL = '<td class=today>';
			var TDstart = '<td>';
			var TDend = '</td>';
			var empty = '<td class=empty>&nbsp;</td>';
				
			// preparing date
			var Calendar = new Date();
			var current_day = Calendar.getDate();
			var current_month = Calendar.getMonth();
			var current_year = Calendar.getYear();
			
			if( !month ) { month = Calendar.getMonth(); } else { month = month * 1; }
			var prev_month = month - 1;
			var next_month = month + 1;
			if( !year ) { year = Calendar.getYear(); } else { year = year * 1; }
			year_real = year;
			var prev_year = year;
			if(prev_month < 0) { prev_month = 11; prev_year--; }
			var next_year = year;
			if(next_month > 11) { next_month = 0; next_year++; }
			if(year < 1900) {
				year_real = year + 1900;
			}
			
			// filling header
			var header_string = '<table><tr>';
			header_string += '<td align=left><a href="javascript:datepicker_'+this.instance_id+'.show_month(\''+prev_month+'\', \''+prev_year+'\')">&lt;&lt</a></td>';
			header_string += '<td width=100% align=center><a href="javascript:datepicker_'+this.instance_id+'.show_year(\''+year+'\')">'+this.monthName[month] + ' ' + year_real+'</a></td>';
			header_string += '<td align=right><a href="javascript:datepicker_'+this.instance_id+'.show_month(\''+next_month+'\', \''+next_year+'\')">&gt;&gt</a></td>';
			header_string += '</tr></table>';
			$('datepicker_'+this.instance_id+'_header').innerHTML = header_string;
			
			// filling month
			Calendar.setDate(1);
			Calendar.setMonth( month );
			Calendar.setYear( year_real );
			var cal = '';
			cal += '<table cellspacing=0 class=small>' + TRstart;
			
			// days' names
			for(index = 0; index < 7; index++) {
				cal += '<td class=daysHeader>' + days[index] + TDend;
			}
			cal += TRend + TRstart+'<td class=spacerTop colspan="'+daysInWeek+'"><p class=pt></p></td>'+TRend+TRstart;
			
			// blanks before first day of the month
			var tmp = Calendar.getDay();
			if( tmp == 0 ) { tmp = 7; }
			for(index = 1; index < tmp ; index++) {
				cal += empty;
			}
			var weekday;
			for(index = 0; index < daysInMonth[month]; index++)	{
				weekday = Calendar.getDay();
				if(weekday == 1) { cal += TRstart; }
				
				cal += '<td ';
				if( (current_day == Calendar.getDate()) && (current_month == month) && (current_year == year) ) {
					cal += ' class=today>';
				} else if( ((weekday+1) % 6) < 1) {
					cal += ' class=weekend>';
				} else {
					cal += '>';
				}
				//cal += '<a class=day href="javascript:get_date('+year_real+', '+(month+1)+', '+Calendar.getDate()+', \''+this.field+'\', \''+this.format+'\')">';
				var prep_link = this.link_proto.replace("__YEAR__", year_real);
				prep_link = prep_link.replace("__MONTH__", (month+1));
				prep_link = prep_link.replace("__DAY__", Calendar.getDate());
				cal += '<div class="day"><a href="javascript:void(0)" onClick="'+prep_link+'">';
				cal += Calendar.getDate();
				cal += '</a></div>' + TDend;
				
				if(weekday == 0) { cal += TRend; }
				Calendar.setDate(Calendar.getDate()+1);
			} // end for loop
			
			if( weekday < daysInWeek ) {
				for(index = weekday+1; index < (Calendar.getDay()+6)%7; index++) {
					cal += empty;
				}
				cal += TRend+TRstart+'<td class=spacerBottom colspan='+daysInWeek+'><p class=pt></p></td>'+TRend;
			}
			cal += '</TABLE>';
			// and final solution
			$('datepicker_'+this.instance_id+'_view').innerHTML = cal;
		}
		
		//show a year
		this.show_year = function( year ) {
			// formatting constants
			var TRstart = '<tr>';
			var TRend = '</tr>';
			var TDstartHL = '<td class=today>';
			var TDstart = '<td>';
			var TDend = '</td>';
				
			// preparing date
			var Calendar = new Date();
			var current_month = Calendar.getMonth();
			var current_year = Calendar.getYear();
			if( !year ) { year = Calendar.getYear(); } else { year = year * 1; }
			year_real = year;
			var prev_year = year - 1;
			var next_year = year + 1;
			if(year < 1900) {
				year_real = year + 1900;
			}
			
			// filling header
			var header_string = '<table><tr>';
			header_string += '<td align=left><a href="javascript:datepicker_'+this.instance_id+'.show_year(\''+prev_year+'\')">&lt;&lt</a></td>';
			header_string += '<td width=100% align=center><a href="javascript:datepicker_'+this.instance_id+'.show_decade(\''+(year - (year%10))+'\')">'+ year_real+'</a></td>';
			header_string += '<td align=right><a href="javascript:datepicker_'+this.instance_id+'.show_year(\''+next_year+'\')">&gt;&gt</a></td>';
			header_string += '</tr></table>';
			document.getElementById('datepicker_'+this.instance_id+'_header').innerHTML = header_string;
			
			// filling year with months	
			var cal = '';
			cal += '<table cellspacing=0 class=small>';		
			for(index = 0; index < 12; index++)	{
				if( index % 3 == 0 ) { cal += TRstart; }
				
				cal += '<td ';
				if( (current_month == index) && (current_year == year) ) {
						cal += ' class=today>';
				} else {
					cal += '>';
				}
				cal += '<div class="month"><a href="javascript:datepicker_'+this.instance_id+'.show_month('+index+', '+year+')">';
				cal += this.monthName[index];
				cal += '</a></div>' + TDend;
				
				if(index % 3 == 2) { cal += TRend; }
			} // end for loop
			
			cal += '</TABLE>';
			// and final solution
			document.getElementById('datepicker_'+this.instance_id+'_view').innerHTML = cal;
		}
		
		//show a decade
		this.show_decade = function( decade ) {
			// formatting constants
			var TRstart = '<tr>';
			var TRend = '</tr>';
			var TDstartHL = '<td class=today>';
			var TDstart = '<td>';
			var TDend = '</td>';
				
			// preparing date
			var Calendar = new Date();
			var current_year = Calendar.getYear();
			if( !decade ) { decade = Calendar.getYear(); } else { decade = decade * 1; }
			decade_real = decade;
			var prev_decade = decade - 10;
			var next_decade = decade + 10;
			if(decade < 1900) {
				decade_real = decade + 1900;
			}
			
			// filling header
			var header_string = '<table><tr>';
			header_string += '<td align=left><a href="javascript:datepicker_'+this.instance_id+'.show_decade(\''+prev_decade+'\')">&lt;&lt</a></td>';
			header_string += '<td width=100% align=center><a href="javascript:datepicker_'+this.instance_id+'.show_century(\''+(decade - (decade%100))+'\')">'+ decade_real + ' - ' + (decade_real+10) + '</a></td>';
			header_string += '<td align=right><a href="javascript:datepicker_'+this.instance_id+'.show_decade(\''+next_decade+'\')">&gt;&gt</a></td>';
			header_string += '</tr></table>';
			document.getElementById('datepicker_'+this.instance_id+'_header').innerHTML = header_string;
			
			// filling year with months	
			var cal = '';
			cal += '<table cellspacing=0 class=small>';		
			for(index = 0; index < 12; index++)	{
				if( index % 3 == 0 ) { cal += TRstart; }
				
				cal += '<td ';
				if( current_year == decade + index -1 ) {
						cal += ' class=today>';
				} else {
					cal += '>';
				}
				cal += '<div class="month"><a href="javascript:datepicker_'+this.instance_id+'.show_year(' + (decade + index - 1) + ')">';
				cal += (decade_real + index - 1);
				cal += '</a></div>' + TDend;
				
				if(index % 3 == 2) { cal += TRend; }
			} // end for loop
			
			cal += '</TABLE>';
			// and final solution
			document.getElementById('datepicker_'+this.instance_id+'_view').innerHTML = cal;
		}
		
		//show a century
		this.show_century = function( century ) {
			// formatting constants
			var TRstart = '<tr>';
			var TRend = '</tr>';
			var TDstartHL = '<td class=today>';
			var TDstart = '<td>';
			var TDend = '</td>';
				
			// preparing date
			var Calendar = new Date();
			var current_year = Calendar.getYear();
			if( !century ) { century = Calendar.getYear(); } else { century = century * 1; }
			century_real = century;
			var prev_century = century - 100;
			var next_century = century + 100;
			if(century < 1900) {
				century_real = century + 1900;
			}
			
			// filling header
			var header_string = '<table><tr>';
			header_string += '<td align=left><a href="javascript:datepicker_'+this.instance_id+'.show_century(\''+prev_century+'\')">&lt;&lt</a></td>';
			header_string += '<td width=100% align=center>'+ century_real + ' - ' + (century_real+100) + '</td>';
			header_string += '<td align=right><a href="javascript:datepicker_'+this.instance_id+'.show_century(\''+next_century+'\')">&gt;&gt</a></td>';
			header_string += '</tr></table>';
			document.getElementById('datepicker_'+this.instance_id+'_header').innerHTML = header_string;
			
			// filling year with months	
			var cal = '';
			cal += '<table cellspacing=0 class=small>';		
			for(index = 0; index < 120; index += 10)	{
				if( index % 30 == 0 ) { cal += TRstart; }
				
				cal += '<td ';
				if( (current_year > century + index - 10) && (century + index > current_year) ) {
						cal += ' class=today>';
				} else {
					cal += '>';
				}
				cal += '<div class="month"><a href="javascript:datepicker_'+this.instance_id+'.show_decade(' + (century + index - 10) + ')">';
				cal += (century_real + index - 10) + ' - ' + (century_real + index);
				cal += '</a></div>' + TDend;
				
				if(index % 30 == 20) { cal += TRend; }
			} // end for loop
			
			cal += '</TABLE>';
			// and final solution
			document.getElementById('datepicker_'+this.instance_id+'_view').innerHTML = cal;
		}
	}
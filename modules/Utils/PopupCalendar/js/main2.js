var Utils_PopupCalendar = function(link_proto, instance_id, mode,first_day_of_week, month_names, day_names) {
		this.monthName = month_names;
		this.link_proto = link_proto;
		this.selected = 0;
		this.instance_id = instance_id;
		if(typeof mode == 'undefined') mode='day';
		this.mode = mode;
		this.first_day_of_week = first_day_of_week;
		if(typeof first_day_of_week == 'undefined')
			this.first_day_of_week = 0;
		else
			this.first_day_of_week = parseInt(this.first_day_of_week);

		//show calendar
		this.show = function(year, month, day) {
			switch(this.mode) {
				case 'year':
					this.show_decade();
					break;
				case 'month':
					this.show_year(year);
					break;
				case 'day':
				default:
					this.show_month(year, month, day);
			}
		}

		// show a month
		this.show_month = function( year, month, day ) {
			if (!this.selected && year && month && day) {
				this.selected = new Date();
				this.selected.setDate(day);
				this.selected.setMonth(month);
				this.selected.setYear(year);
			}
			var days = day_names;
            var daysInMonth = null;
			var daysInWeek = 7;

			// formatting constants
			var TRstart = '<tr>';
			var TRend = '</tr>';
			var TDstartHL = '<td class="today">';
			var TDstart = '<td>';
			var TDend = '</td>';
			var empty = '<td class="empty">&nbsp;</td>';

			// preparing date
			var Calendar = new Date();
			var current_day = Calendar.getDate();
			var current_month = Calendar.getMonth();
			var current_year = Calendar.getFullYear();
			if( typeof month == "undefined" ) { month = Calendar.getMonth(); } else { month = month * 1; }
			var prev_month = month - 1;
			var next_month = month + 1;
			if( typeof year == "undefined" ) { year = Calendar.getFullYear(); } else { year = year * 1; }

            if(year%400==0 || (year%100!=0 && year%4==0))
            	daysInMonth = new Array(31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
            else
            	daysInMonth = new Array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);

			year_real = year;
			var prev_year = year;
			if(prev_month < 0) { prev_month = 11; prev_year--; }
			var next_year = year;
			if(next_month > 11) { next_month = 0; next_year++; }

			// filling header
			var header_string = '<table class="menu" cellspacing="0" cellpadding="0" border="0"><tr>';
			header_string += '<td class="prev"><a href="javascript:void(0)" onClick="datepicker_'+this.instance_id+'.show_month(\''+prev_year+'\', \''+prev_month+'\')">&lt;&lt</a></td>';
			header_string += '<td class="month"><a href="javascript:void(0)" onClick="datepicker_'+this.instance_id+'.show_year(\''+year+'\')">'+this.monthName[month] + ' ' + year_real+'</a></td>';
			header_string += '<td class="next"><a href="javascript:void(0)" onClick="datepicker_'+this.instance_id+'.show_month(\''+next_year+'\', \''+next_month+'\')">&gt;&gt</a></td>';
			header_string += '</tr></table>';
			$('datepicker_'+this.instance_id+'_header').innerHTML = header_string;

			// filling month
			Calendar.setDate(1);
			Calendar.setMonth( month );
			Calendar.setYear( year_real );
			var cal = '';
			cal += '<table cellspacing="0" cellpadding="0" border="0" class="small">' + TRstart;

			// days' names
			for(index = 0; index < 7; index++) {
				cal += '<td class="daysHeader">' + days[(index+this.first_day_of_week)%7] + TDend;
			}
			cal += TRend + TRstart+'<td class="spacerTop" colspan="'+daysInWeek+'"><p class="pt"></p></td>'+TRend+TRstart;

			// blanks before first day of the month
			var tmp = Calendar.getDay();
			if( tmp == 0 ) { tmp = 7; }
			for(index = this.first_day_of_week; index < tmp ; index++) {
//			for(index = 1; index < tmp ; index++) {
				cal += empty;
			}
			var weekday;
			for(index = 0; index < daysInMonth[month]; index++)	{
				weekday = Calendar.getDay();
				//if(weekday == 1) { cal += TRstart; }
				if(weekday == this.first_day_of_week) { cal += TRstart; }

				cal += '<td class="';
				if( (current_day == Calendar.getDate()) && (current_month == month) && (current_year == year) )
					cal += 'today ';
				if( weekday % 6 < 1 )
					cal += 'weekend ';
				if( this.selected &&
					(Calendar.getDate() == this.selected.getDate()) && 
					(Calendar.getMonth() == this.selected.getMonth()) && 
					(Calendar.getFullYear() == this.selected.getFullYear()) )
					cal += 'selected ';
				cal += '">';
				//cal += '<a class=day href="javascript:get_date('+year_real+', '+(month+1)+', '+Calendar.getDate()+', \''+this.field+'\', \''+this.format+'\')">';
				var prep_link = this.link_proto.replace("__YEAR__", year_real);
				prep_link = prep_link.replace("__MONTH__", (month+1));
				prep_link = prep_link.replace("__DAY__", Calendar.getDate());
				cal += '<div class="day"><a href="javascript:void(0)" onClick="datepicker_'+this.instance_id+'.selected = new Date('+Calendar.getFullYear()+','+Calendar.getMonth()+','+Calendar.getDate()+');datepicker_'+this.instance_id+'.show_month('+year+','+month+','+day+');'+prep_link+'">';
				cal += Calendar.getDate();
				cal += '</a></div>' + TDend;

//				if(weekday == 0) { cal += TRend; }
				if(weekday == (this.first_day_of_week+6)%7) { cal += TRend; }
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
			var current_year = Calendar.getFullYear();
			if( !year ) { year = Calendar.getFullYear(); } else { year = year * 1; }
			year_real = year;
			var prev_year = year - 1;
			var next_year = year + 1;

			// filling header
			var header_string = '<table class="menu" cellspacing="0" cellpadding="0" border="0"><tr>';
			header_string += '<td align=left><a href="javascript:void(0)" onClick="datepicker_'+this.instance_id+'.show_year(\''+prev_year+'\')">&lt;&lt</a></td>';
			header_string += '<td width=100% align=center><a href="javascript:void(0)" onClick="datepicker_'+this.instance_id+'.show_decade(\''+(year - (year%10))+'\')">'+ year_real+'</a></td>';
			header_string += '<td align=right><a href="javascript:void(0)" onClick="datepicker_'+this.instance_id+'.show_year(\''+next_year+'\')">&gt;&gt</a></td>';
			header_string += '</tr></table>';
			document.getElementById('datepicker_'+this.instance_id+'_header').innerHTML = header_string;

			// filling year with months
			var cal = '';
			cal += '<table cellspacing="0" cellpadding="0" border="0" class="small">';
			for(index = 0; index < 12; index++)	{
				if( index % 3 == 0 ) { cal += TRstart; }

				cal += '<td ';
				if( (current_month == index) && (current_year == year) ) {
						cal += ' class=today>';
				} else {
					cal += '>';
				}
				var prep_link;
				if(this.mode!='month') {
					prep_link = 'datepicker_'+this.instance_id+'.show_month('+year+', '+index+')';
				} else {
					prep_link = this.link_proto.replace("__YEAR__", year_real);
					prep_link = prep_link.replace("__MONTH__", (index+1));
					prep_link = prep_link.replace("__DAY__", '1');
				}
				cal += '<div class="month"><a href="javascript:void(0)" onClick="'+prep_link+'">';
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
			var current_year = Calendar.getFullYear();
			if( !decade ) { decade = Calendar.getFullYear(); } else { decade = decade * 1; }
			decade_real = decade;
			var prev_decade = decade - 10;
			var next_decade = decade + 10;

			// filling header
			var header_string = '<table class="menu"  cellspacing="0" cellpadding="0" border="0"><tr>';
			header_string += '<td align=left><a href="javascript:void(0)" onClick="datepicker_'+this.instance_id+'.show_decade(\''+prev_decade+'\')">&lt;&lt</a></td>';
			header_string += '<td width=100% align=center><a href="javascript:void(0)" onClick="datepicker_'+this.instance_id+'.show_century(\''+(decade - (decade%100))+'\')">'+ decade_real + ' - ' + (decade_real+10) + '</a></td>';
			header_string += '<td align=right><a href="javascript:void(0)" onClick="datepicker_'+this.instance_id+'.show_decade(\''+next_decade+'\')">&gt;&gt</a></td>';
			header_string += '</tr></table>';
			document.getElementById('datepicker_'+this.instance_id+'_header').innerHTML = header_string;

			// filling year with months
			var cal = '';
			cal += '<table cellspacing="0" cellpadding="0" border="0" class="small">';
			for(index = 0; index < 12; index++)	{
				if( index % 3 == 0 ) { cal += TRstart; }

				cal += '<td ';
				if( current_year == decade + index -1 ) {
						cal += ' class=today>';
				} else {
					cal += '>';
				}
				var prep_link;
				if(this.mode!='year') {
					prep_link = 'datepicker_'+this.instance_id+'.show_year('+(decade+index-1)+')';
				} else {
					prep_link = this.link_proto.replace("__YEAR__", (decade_real+index-1));
					prep_link = prep_link.replace("__MONTH__", '1');
					prep_link = prep_link.replace("__DAY__", '1');
				}
				cal += '<div class="month"><a href="javascript:void(0)" onClick="'+prep_link+'">';
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
			var current_year = Calendar.getFullYear();
			if( !century ) { century = Calendar.getFullYear(); } else { century = century * 1; }
			century_real = century;
			var prev_century = century - 100;
			var next_century = century + 100;

			// filling header
			var header_string = '<table class="menu" cellspacing="0" cellpadding="0" border="0"><tr>';
			header_string += '<td align=left><a href="javascript:void(0)" onClick="datepicker_'+this.instance_id+'.show_century(\''+prev_century+'\')">&lt;&lt</a></td>';
			header_string += '<td width=100% align=center>'+ century_real + ' - ' + (century_real+100) + '</td>';
			header_string += '<td align=right><a href="javascript:void(0)" onClick="datepicker_'+this.instance_id+'.show_century(\''+next_century+'\')">&gt;&gt</a></td>';
			header_string += '</tr></table>';
			document.getElementById('datepicker_'+this.instance_id+'_header').innerHTML = header_string;

			// filling year with months
			var cal = '';
			cal += '<table cellspacing="0" cellpadding="0" border="0" class="small">';
			for(index = 0; index < 120; index += 10)	{
				if( index % 30 == 0 ) { cal += TRstart; }

				cal += '<td ';
				if( (current_year > century + index - 10) && (century + index > current_year) ) {
						cal += ' class=today>';
				} else {
					cal += '>';
				}
				cal += '<div class="month"><a href="javascript:void(0)" onClick="datepicker_'+this.instance_id+'.show_decade(' + (century + index - 10) + ')">';
				cal += (century_real + index - 10) + '&nbsp;-&nbsp;' + (century_real + index);
				cal += '</a></div>' + TDend;

				if(index % 30 == 20) { cal += TRend; }
			} // end for loop

			cal += '</TABLE>';
			// and final solution
			document.getElementById('datepicker_'+this.instance_id+'_view').innerHTML = cal;
		}
	}

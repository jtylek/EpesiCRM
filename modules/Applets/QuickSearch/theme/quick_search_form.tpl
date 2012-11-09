</center>
<left>
	<div class="epesi_caption">
		{$header}
	</div>
</left>	
<div class="table">
	<div class="layer">
		<div class="css3_content_shadow">
			<div id="margin2px" style="text-align:left;padding:0 10px 0;">		
				{$form_open}
					<br>
					<table>
						<tr>
							<td class="epesi_label" style="width:200px">
								{$form_data.alias_name.label}
							</td>
							<td class="epesi_data" style="width:400px">
								{$form_data.alias_name.html}
							</td>						
						</tr>	
						<tr>	
							<td class="epesi_label" style="width:200px">
								{$form_data.recordsetfrom.label}
							</td>
							<td class="epesi_data" style="width:450px">								
								<table id="multiselect">
									<tr>
										<td class="form-element" style="width:70px" align="left">{$form_data.recordsetfrom.html}</td>
										<td class="form-element" style="width:30px" align="center">{$form_data.record_btn_copy.html}<br>
											{$form_data.record_btn_revert.html}<br>
										</td>
										<td class="form-element" style="width:70px" align="left">{$form_data.recordsetto.html}</td>
									</tr>
								</table>
								
							</td>						
						</tr>	
						<tr>				
							<td class="epesi_label" style="width:200px">
								{$form_data.fieldsfrom.label}
							</td>				
							<td class="epesi_data" style="width:450px">					
								<table id="multiselect">
									<tr>
										<td class="form-element" style="width:70px" align="left">{$form_data.fieldsfrom.html}</td>
										<td class="form-element" style="width:30px" align="center">{$form_data.fields_btn_copy.html}<br>
											{$form_data.fields_btn_revert.html}<br>
										</td>
										<td class="form-element" style="width:70px" align="left">{$form_data.fieldsto.html}</td>
									</tr>
								</table>		
		
							</td>							
						</tr>	
						<tr>				
							<td class="epesi_label" style="width:200px">
								{$form_data.search_format.label}
							</td>
							<td class="epesi_data" style="width:400px">
								{$form_data.search_format.html}
							</td>						
						</tr>						
						<tr>				
							<td class="epesi_label" style="width:200px">
								{$form_data.placeholder.label}
							</td>
							<td class="epesi_data" style="width:400px">
								{$form_data.placeholder.html}
							</td>						
						</tr>								
						<tr>				
							<td class="epesi_label" style="width:200px">
								{$form_data.status.label}
							</td>
							<td class="epesi_data"  style="width:400px">
								{$form_data.status.html}
							</td>						
						</tr>						
					</table>
				{$form_close}
			</div>
		</div>
	</div>		
</div>
(function( $ ) {
	

	$('.content-excel-importer .nav-tab-wrapper a').click(function(e){
		e.preventDefault();
		if($(this).hasClass("premium") ){
			$(".premium_msg").slideDown('slow');
			$(".freeContent").fadeOut('slow');
		}else{
			$(".premium_msg").hide();
			$(".freeContent").fadeIn('slow');		
			window.history.replaceState("object or string", "Title", $(this).attr("href"));			
		}
	
	});	


	
	
	$('.content-excel-importer #upload').attr('disabled','disabled');
    $(".content-excel-importer #file").change(function () {
        var fileExtension = ['xls', 'xlsx'];
        if ($.inArray($(this).val().split('.').pop().toLowerCase(), fileExtension) == -1) {
            alert("Only format allowed: "+fileExtension.join(', '));	
			$(".content-excel-importer input[type='submit']").attr('disabled','disabled');
        }else{
			$(".content-excel-importer input[type='submit']").removeAttr('disabled');
			$(".content-excel-importer").find('form').submit();						
		}
    });
	
	
	$(".content-excel-importer #selectPostType").on("change", function (e) {
		
		
		$(".content-excel-importer #product_import").hide();
		$('.content-excel-importer .product_content').hide();
		$(".content-excel-importer #product_process").hide();	
		$('.content-excel-importer #selectPostType').submit();	
	});
			
	$(".content-excel-importer #selectPostType").on("submit", function (e) {		
		e.preventDefault();
		localStorage.setItem('postItem',$("#selectPostType #contentExcelImporter_post_type ").val() );
		data = $(this).serialize();
			$.ajax({
				url: $(this).attr('action'),
				data:  data,
				type: 'POST',
				beforeSend: function() {								
					$('.content-excel-importer').addClass('loading');
				},						
				success: function(response){
					$(".vocabularySelect").slideDown().html($(response).find(".vocabularySelect").html());
					$('.content-excel-importer').removeClass('loading');
					$(".content-excel-importer #selectPostType").val(localStorage.getItem('postItem'));
					$(".content-excel-importer #getPostType").val(localStorage.getItem('postItem'));					
					$(".getMetaKeys").slideDown().html($(response).find(".getMetaKeys").html());					
					$('.content-excel-importer .excel_import').fadeIn();
					
					if(localStorage.getItem('postItem')=='product'){							
						$('.content-excel-importer .product_content').fadeIn();
						$('.content-excel-importer .randomContent').hide();
					}else $('.content-excel-importer .product_content').hide();
					//$('#selectPostType').remove();
					//runAfterAjax();
				}
			});				
	});	



			//drag and drop
			function dragDrop(){
				$('.content-excel-importer .draggable').draggable({cancel:false});
				$( ".content-excel-importer .droppable" ).droppable({
				  drop: function( event, ui ) {
					$( this ).addClass( "ui-state-highlight" ).val( $( ".ui-draggable-dragging" ).val() );
					$( this ).attr('value',$( ".ui-draggable-dragging" ).attr('key')); //ADDITION VALUE INSTEAD OF KEY	
					$( this ).val($( ".ui-draggable-dragging" ).attr('key') ); //ADDITION VALUE INSTEAD OF KEY
					$( this ).attr('placeholder',$( ".ui-draggable-dragging" ).attr('value')); 				
					$( ".ui-draggable-dragging" ).css('visibility','hidden'); //ADDITION + LINE
					$( this ).css('visibility','hidden'); //ADDITION + LINE
					// alert($(this).attr('key'));
					$( this ).parent().css('background','#90EE90');	
					
				  }		 
				});		
			}
	
			function automatch_columns(){
				
				$(".content-excel-importer #automatch_columns").on("change",function(){

					if($(".content-excel-importer #automatch_columns").is(':checked')){
						
						$( ".content-excel-importer .draggable" ).each(function(){
							
							var key = $( this ).attr('key') ; 
							key = key.toUpperCase();
							key = key.replace(" ", "_");							
							var valDrag = $( this ).val() ;
							
							
							$( ".content-excel-importer .droppable" ).each(function(){
								
								var valDrop = $( this ).val();
								
								var drop = $( this ).attr('name');
								
								drop.indexOf( '_' ) == 0 ? drop = drop.replace( '_', '' ) : drop;
																
								var drop = drop.replace(/_/g, " ");								
								var nameDrop = drop.toUpperCase();
																
								if( valDrag == nameDrop ){
								
									$( this ).val( key );
									
									//$( valDrag ).css('visibility','hidden'); //ADDITION + LINE
									$( this ).css('background','#90EE90');
									$( this ).parent().css('background','#90EE90');	
								}
							});							
						});

						alert("Check your automatch - The letter after the match signifies the Excel Column Letter. If not satisfied you can always uncheck auto match and do manually");
				
					}else{
						$( ".content-excel-importer .droppable" ).val('');
						$( ".content-excel-importer .droppable" ).css('background','initial');
						$( ".content-excel-importer .droppable" ).parent().css('background','initial');	
					}
												
				});			
			}

	
	$(".content-excel-importer #product_import").on("submit", function (e) {
		e.preventDefault();
				var data = new FormData();
				$.each($('#file')[0].files, function(i, file) {
					data.append('file', file);
				});	
				data.append('_wpnonce',$("#_wpnonce").val());
				data.append('importProducts',$("#importProducts").val() );
				data.append('getPostType', localStorage.getItem('postItem') );
								
				url= window.location.href;
				$.ajax({
					url: window.location.href,
					data: data,
					cache: false,
					contentType: false,
					processData: false,
					type: 'POST',
					beforeSend: function() {	
						$("html, body").animate({ scrollTop: 0 }, "slow");
						$('.content-excel-importer').addClass('loading');	
					},					
					success: function(response){
										
						$(".content-excel-importer .result").slideDown().html($(response).find(".result").html());
						$('.content-excel-importer').removeClass('loading');	
						$("#product_import").fadeOut();																								
						
						dragDrop();	
						automatch_columns();	
						

						$('.content-excel-importer #product_process p.proVersion').hover(function(){
							$(this).css("background","red");
							$(this).css("cursor","pointer");
						});	
						$('.content-excel-importer #product_process p.proVersion').click(function(){
							window.open('https://extend-wp.com/product/content-importer-wordpress-woocommerce-excel/', '_blank');
						});
						
						$(".content-excel-importer #product_process").on('submit',function(e) {
							e.preventDefault();
							if($("input[name='post_title']").val() !='' ){								
								$(".progressText").fadeIn();
								total = $(".importer-wrap-pro input[name='finalupload']").val() ;
								$(".content-excel-importer .total").html(total-1);
								var i = 2;	
								$('.content-excel-importer').addClass('loading');
								function importProducts() {
										
									start = parseInt($(".content-excel-importer input[name='start']").val() );
									total = parseInt( $(".content-excel-importer input[name='finalupload']").val() ) ;
									console.log("dtart at: "+start + "  - total : " + total);
									if(start > total  ){
										$('.content-excel-importer .success , .content-excel-importer .error, .content-excel-importer .warning').delay(2000).hide();
										$(".content-excel-importer #product_import").delay(5000).slideDown();
									}else{	
										
										$.ajax({
											url: contentExcelImporter.ajax_url,
											data: $(".content-excel-importer #product_process").serialize(),
											type: 'POST',
											beforeSend: function() {
												$("html, body").animate({ scrollTop: 0 }, "slow");
												
												$(".content-excel-importer #product_process").hide();
											},						
											success: function(response){
												//$(".content-excel-importer .translations").slideDown().html($(response).find(".translations").html());
												
												$(".content-excel-importer .importMessage").slideDown().html($(response).find(".importMessage").html());
												
												$(".content-excel-importer .ajaxResponse").html(response);
												$(".content-excel-importer .thisNum").html($("#AjaxNumber").html() );
												
													$(".content-excel-importer input[name='start']").val(i + 1 );
													i++;
													
											},complete: function(response){
													$('.content-excel-importer').removeClass('loading');
													importProducts();
											}
										});	
									
									}
								}
								
								importProducts();
							}else alert('Title Selection is Mandatory.');
							
						});	
					
					}
		
			});			
	});	

		

			function progressBar(start,total) {
				width = (start/total) * 100;
			  var elem = document.getElementById("myBar");   
				if (start >= total-1) {
				  //clearInterval(id);
				  elem.style.width = '100%'; 
				} else {
				  start++; 
				  elem.style.width = width + '%'; 
				}
			}	


		$("#contentExceIimporter_signup").on('submit',function(e){
			e.preventDefault();	
			var dat = $(this).serialize();
			$.ajax({
				
				url:	"https://extend-wp.com/wp-json/signups/v2/post",
				data:  dat,
				type: 'POST',							
				beforeSend: function(data) {								
						console.log(dat);
				},					
				success: function(data){
					alert(data);

				},
				complete: function(data){
					contentExceIimporter();
				}				
			});	
		});

		function contentExceIimporter(){
			
				var ajax_options = {
					action: 'contentExceIimporter_push_not',
					data: 'title=1',
					nonce: 'contentExceIimporter_push_not',
					url: contentExcelImporter.ajax_url,
				};			
				
				$.post( contentExcelImporter.ajax_url, ajax_options, function(data) {
					$(".contentExceIimporter_notification").fadeOut();
				});
		}
		
		$(".contentExceIimporter_notification .dismiss").on('click',function(e){
				var ajax_options = {
					action: 'contentExceIimporter_push_not',
					data: 'title=1',
					nonce: 'contentExceIimporter_push_not',
					url: contentExcelImporter.ajax_url,
				};			
				
				$.post( contentExcelImporter.ajax_url, ajax_options, function(data) {
					$(".contentExceIimporter_notification").fadeOut();
				});
		});

})( jQuery )
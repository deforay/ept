function preloadImages(imgs){
	
	var picArr = [];
	
		for (var i = 0; i<imgs.length; i++){
			
				picArr[i]= new Image(100,100); 
				picArr[i].src=imgs[i]; 

			
			}
	
	}
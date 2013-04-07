function addToUploadifyScriptData() {
  // TODO: reset scriptData first? empty stuff is likely not getting added again to override non-empty, does it?
  
  // merge values, do not override simply (would break other module dependencies like tags)
  var scriptData = $('#g-uploadify').uploadifySettings('scriptData');

  // first clear all cf_ entries, then readd them all
  var newArr = {};
  $.each(scriptData, function(index, myobj) {
  	var match = (index.substring(3,0) == 'cf_');
  	if ( !match )
  	{
	    newArr[index] = myobj;
    } else {
    	newArr[index] = null;
    }
  }); 
  scriptData = newArr;

  var jsArray = $('#g-add-photos-form').serializeArray();
  $.each(jsArray, function(index, myobj) { 
    // workaround against nutsdriving js issue when trying to define array in uploadifySettings directly, doh!
    var jsonArr = {};
    jsonArr[myobj.name] = myobj.value;
    
    $.extend(scriptData, jsonArr);
    $('#g-uploadify').uploadifySettings('scriptData', scriptData);
  });
}
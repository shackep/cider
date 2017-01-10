var fetch_external_resource = function(url){
    var externalResource = new XMLHttpRequest();
    externalResource.open('GET', url, true);
    externalResource.send();

    externalResource.addEventListener("readystatechange", processRequest, false);
    function processRequest(e) {
        if (externalResource.readyState == 4 && externalResource.status == 200) {
            var response = JSON.parse(externalResource.responseText);
            console.log(response);
        }
    }
}
fetch_external_resource('http://pixelplow.com/adding-custom-meta-boxes/');
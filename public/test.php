<script src="//cdn.bootcss.com/jquery/3.3.1/jquery.min.js"></script>
<form id="form" enctype="multipart/form-data" method="post" action="https://yunde.jrytc.cn/api.php/Login/upload" onsubmit="return false;">
	<input type="file" name="file">
	<input type="button" onclick="get();" name="" value="提ee交">
</form>
<script>
function get(){
    var form = new FormData(document.getElementById("form"));
    console.log(form); return false;
    $.ajax({
        type: "post",
        url:"https://yunde.jrytc.cn/api.php/File/upload",
        contentType: "application/json;charset=utf-8",
        data : form,
        dataType: "json",
        beforeSend: function (XMLHttpRequest) {
            XMLHttpRequest.setRequestHeader("token", "29afX4pGFPUGGnrAoNOdgtusJsoypo4AYEh4J6I6GqngNgjpbzOGVsGQfQ");
        },
        success: function (data) {
            alert(data);
        },error:function(error){
            console.log(error);
        }
    });
    alert(2);
}
</script>
<?php 

?>
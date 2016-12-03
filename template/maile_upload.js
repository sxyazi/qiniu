function maile_upload(uptype){

	this.file = null;
	this.form = null;
	this.bbda = null;

	this.files = [];
	this.iframe = [];

	var maile = this;
	uptype = Math.min(uptype, 2);

	/**
	 * 初始化
	 * @param  {[type]} bbda [description]
	 * @return {[type]}      [description]
	 */
	this.init = function(bbda){
		var fmid = maile_upload.rand();
		var upid = maile_upload.rand();
		var str = "<form id='" + fmid + "' enctype='multipart/form-data' action='http://upload.qiniu.com/' method='POST' accept-charset='utf-8'>";
		str += "<input  id='" + upid + "' name='file' type='file' value='" + maile_upload_config.lang.uploadImg + "' />";
		str += "</form>";
		if(uptype == 2){
			(this.bbda = bbda).innerHTML += str;
			document.getElementById(upid).style.display = "none";
		}else{
			(this.bbda = bbda).innerHTML = str;
		}
		this.bind(document.getElementById(upid), document.getElementById(fmid));
		this.add();
	}

	/**
	 * 添加新上传域
	 */
	this.add = function(){
		var n = maile_upload.rand();
		var ifm = document.createElement("iframe");
		ifm.setAttribute("name", n);
		ifm.setAttribute("width", "0");
		ifm.setAttribute("height", "0");
		ifm.setAttribute("frameBorder", "0");
		this.bbda.appendChild(ifm);
		this.form.setAttribute("target", n);
		this.iframe.push(n);
		var ifmid = this.iframe.length - 1;
		this.bind(this.file, this.form, ifmid);
		return ifmid;
	}

	/**
	 * 绑定事件
	 * @param  {[type]} file [description]
	 * @param  {[type]} form [description]
	 * @return {[type]}      [description]
	 */
	this.bind = function(file, form, ifmid){

		this.file = file;
		this.form = form;

		if(ifmid == undefined)
			return;

		// 文件被改变
		this.file.onchange = function(){

			if(!this.files){
				if(!this.value)
					return;
				this.files = {
					0: {
						name: this.value,
						size: 2
					},
					length: 1
				}
			}

			if(this.files.length < 1)
				return;

			if(this.files[0].size > (maile_upload_config.max * 1024)){
				showError(maile_upload_config.lang.notExceed + " " + maile_upload_config.max + " " + maile_upload_config.lang.byte);
				return;
			}

			var ext = maile_upload.getExt(this.files[0].name).toLowerCase();
			var types = new Array(maile_upload_config.imageext, maile_upload_config.attachext, maile_upload_config.attachext);
			if(types[uptype]!="*.*;" && types[uptype].indexOf("*"+ext+";")==-1){
				showError(maile_upload_config.lang.onlyAllow + " " + types[uptype] + " " + maile_upload_config.lang.typeFile);
				return;
			}

			maile.files[ifmid] = {
				id: "maile_upload_" + maile_upload.rand(),
				name: this.files[0].name,
				size: this.files[0].size,
				type: ext,
				filestatus: -1
			};

			if(uptype){
				maile.post = new Object;
				maile.files[ifmid].index = ifmid;
				maile.files[ifmid].uploadtype = 0;
				maile.files[ifmid].creationdate = maile.files[ifmid].modificationdate = new Date();
			}

			// 准备上传
			maile.setStatus(0, maile.files[ifmid]);
			// fileDialogComplete(1, 1);

			// 获取Token
			var xhr = maile_upload.xhr();
			xhr.open("POST", "plugin.php?id=qiniu:token", true);
			xhr.onreadystatechange = function(){
				if(xhr.readyState == 4){
					if(xhr.status==200 && xhr.responseText){
						if(maile.form.token){
							if(maile.form.token.length > 1){
								for(var i=0; i<maile.form.token.length; i++)
									maile.form.removeChild(maile.form.token[i]);
							}else{
								maile.form.removeChild(maile.form.token);
							}
						}

						token = document.createElement("input");
						token.setAttribute("name", "token");
						token.setAttribute("type", "hidden");
						token.setAttribute("value", xhr.responseText);

						maile.form.appendChild(token);
						maile.form.submit();

						// 正在上传
						maile.files[ifmid].filestatus = -2;
						maile.setStatus(1, maile.files[ifmid], maile.files[ifmid].size/2, maile.files[ifmid].size);
					}else{
						alert(maile_upload_config.lang.checkNetwork);
					}
				}
			}
			xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
			xhr.send("hash=" + maile_upload_config.hash + "&maile=" + (uptype>1 ? uptype+1 : uptype) + (uptype>1 ? "&fid="+fid : ""));
		}

		// 上传完毕
		document.getElementsByName(this.iframe[ifmid])[0].onload = function(){
			if(this.contentWindow.location.href.substr(0, 4).toLowerCase() != 'http')
				return;
			var data = this.contentWindow.document.body.innerHTML;
			if(data){
				data = eval("(" + data + ")");
				maile.files[ifmid].filestatus = -4;
				maile.setStatus(2, maile.files[ifmid], data.id);
				console.log(data, maile.files[ifmid]);
			}else{
				alert(maile_upload_config.lang.uploadError + " NRSK01");
			}
		}

	}

	/**
	 * 设置状态
	 * @param {[type]} status 0. 准备; 1. 开始; 2. 完成
	 * @param {[type]} param  [description]
	 */
	this.setStatus = function(status, param){
		var arr = [];
		var obj = (uptype==1||uptype==2) ? upload : imgUpload;
		for(var i=1; i<arguments.length; i++){
			arr.push(arguments[i]);
		}
		switch(status){
			case 0:
				fileDialogStart.apply(obj, arr);
				fileQueued.apply(obj, arr);
				if(uploadStart)
					uploadStart.apply(obj, arr);
			break;

			case 1:
				uploadProgress.apply(obj, arr);
			break;

			case 2:
				uploadSuccess.apply(obj, arr);
				if(uploadComplete)
					uploadComplete.apply(obj, arr);
			break;
		}
	}

}

maile_upload.rand = function(){
	var chars = "ABCDEFGHJKMNPQRSTWXYZabcdefhijkmnprstwxyz2345678";
	var maxPos = chars.length;
	var key = '';
	for(i=0; i<15; i++) {
		key += chars.charAt(Math.floor(Math.random() * maxPos));
	}
	return "mlg8.cc_" + key + Math.random();
}

maile_upload.xhr = function(){
	var xmlHttp = false;
	try{
		xmlHttp = new ActiveXObject("Msxml2.XMLHTTP");			// ie msxml3.0+（IE7.0及以上）
	}catch(e){
		try{
			xmlHttp = new ActiveXObject("Microsoft.XMLHTTP");		// ie msxml2.6（IE5/6）
		}catch (e){
			xmlHttp = false;
		}
	}
	if(!xmlHttp && typeof XMLHttpRequest != 'undefined'){				// Firefox, Opera 8.0+, Safari
		xmlHttp = new XMLHttpRequest();
	}
	return xmlHttp;
}

maile_upload.getExt = function(name){
	return name.substr(name.lastIndexOf("."));
}

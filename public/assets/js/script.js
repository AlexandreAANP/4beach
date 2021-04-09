function isDev() {
	return defaultIsDev;
}

function dd(el1, el2 = null, el3 = null, el4 = null, el5 = null) {
	if (isDev()) {
		if (el1) { console.log(el1); }
		if (el2) { console.log(el2); }
		if (el3) { console.log(el3); }
		if (el4) { console.log(el4); }
		if (el5) { console.log(el5); }
	}
}

function startToastWorking() {

}

function finishToastWorking() {

}

function showFormMsg(msg, elemRef = null) {
	var strMsg = '<div class="layout-form-card-message">';

	strMsg += '    <div class="layout-form-card-message-title">' + trans('ALERT MESSAGE') + '</div>';
	strMsg += '    <div>';
	strMsg += '        <ul>';

	if (typeof msg === 'object') {
		var strMsgLi = '';
		for (let i = 0; i < msg.length; i++) {
			if (msg[i].trim() !== '') {
				strMsgLi += '        <li class="layout-form-card-message-row">' + msg[i] + '</li>';
			}
		}

		if (strMsgLi === '') {
			strMsgLi += '        <li class="layout-form-card-message-row">Unknown object error</li>';
		}

		strMsg += strMsgLi;

	} else {
		if (msg == '') {
			msg = 'Unknown error';
		}
		strMsg += '        <li class="layout-form-card-message-row">' + msg + '</li>';
	}
	strMsg += '        </ul>';
	strMsg += '    </div>';
	strMsg += '</div>';

	if (elemRef) {
		elemRef.find('.card-header').find('.card-title').hide();
		elemRef.find('.card-header').find('.card-message').html(strMsg);
		elemRef.find('.card-header').find('.card-message').show();

	} else {
		$('.card-header').find('.card-title').hide();
		$('.card-header').find('.card-message').html(strMsg);
		$('.card-header').find('.card-message').show();

		$(window).scrollTop(0);
	}
}

function getIframeWindow(iframe_object) {
	var doc;

	if (iframe_object.contentWindow) {
		return iframe_object.contentWindow;
	}

	if (iframe_object.window) {
		return iframe_object.window;
	}

	if (!doc && iframe_object.contentDocument) {
		doc = iframe_object.contentDocument;
	}

	if (!doc && iframe_object.document) {
		doc = iframe_object.document;
	}

	if (doc && doc.defaultView) {
		return doc.defaultView;
	}

	if (doc && doc.parentWindow) {
		return doc.parentWindow;
	}

	return undefined;
}

window.addEventListener('load', function(e) {
	var options = {
		'apiUrl': arDefaultOptions['apiUrl'],
		'baseUri': arDefaultOptions['baseUri'],
		'cdnUrl': arDefaultOptions['cdnUrl'],
	};

	querybiz.init(options);
});
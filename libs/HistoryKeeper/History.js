/*
unFocus.History, version 2.0 (beta 1) (svn $Revision: 32 $) $Date: 2009-06-13 03:32:05 -0400 (Sat, 13 Jun 2009) $
Copyright: 2005-2009, Kevin Newman (http://www.unfocus.com/)
http://www.opensource.org/licenses/mit-license.php
*/

/*
	Class: unFocus.History
		A singleton with subscriber interface (<unFocus.EventManager>) 
		that keeps a history and provides deep links for Flash and AJAX apps
*/
unFocus.History = (function() {

function Keeper() {
	
	var _this = this,
		// set the poll interval here.
		_pollInterval = 200, _intervalID,
		_currentHash;

	/*
	method: _getHash
		A private method that gets the Hash from the location.hash property.
	 
	returns:
		a string containing the current hash from the url
	*/
	var _getHash = function() {
		return location.hash.substring(1);
	};
	// get initial hash
	_currentHash = _getHash();
	
	/*
	method: _setHash
		A private method that sets the Hash on the location string (the current url).
	*/
	var _setHash = function($newHash) {
		window.location.hash = $newHash;
	};
	
	/*
	method: _watchHash
		A private method that is called every n miliseconds (<_pollInterval>) to check if the hash has changed.
		This is the primary Hash change detection method for most browsers. It doesn't work to detect the hash
		change in IE 5.5+ or various other browsers. Workarounds like the iframe method are used for those 
		browsers (IE 5.0 will use an anchor creation hack).
	*/
	function _watchHash() {
		var $newHash = _getHash();
		if (_currentHash != $newHash) {
			_currentHash = $newHash;
			_this.notifyListeners("historyChange", $newHash);
		}
	}
	// Put the hash check on a timer.
	if (setInterval) _intervalID = setInterval(_watchHash, _pollInterval);
	
	/*
	method: getCurrentBookmark
		A public method to retrieve the current history string.
	
	returns:
		The current History Hash
	*/
	_this.getCurrent = function() {
		return _currentHash;
	};
	
	/*
	method: addHistory
		A public method to add a new history, and set the deep link. This method should be given a string.
		It does no serialization.
	
	returns:
		Boolean - true if supported and set, false if not
	*/
	_this.addHistory = function addHistory($newHash) {
		if (_currentHash != $newHash) {
			_currentHash = $newHash;
			_setHash($newHash);
			_this.notifyListeners("historyChange",$newHash);
		}
		return true;
	};

	/**
	 * These are the platform specific override methods. Since some platforms (IE 5.5+, Safari)
	 * require almost completely different techniques to create history entries, browser detection is
	 * used and the appropriate method is created. The bugs these fixes address are very tied to the
	 * specific implementations of these browsers, and not necessarily the underlying html engines.
	 * Sometimes, bugs related to history management can be tied even to a specific skin in browsers
	 * like Opera.
	 */
	// opera
	if (history.navigationMode)
		history.navigationMode = 'compatible';
	
	// Safari 2.04 and less (and WebKit less than 420 - these hacks are not needed by the most recent nightlies)
	// :TODO: consider whether this aught to check for Safari or WebKit - is this a safar problem, or a does it
	// happen in other WebKit based software? OmniWeb (WebKit 420+) seems to work, though there's a sync issue.
	if (/WebKit\/\d+/.test(navigator.appVersion) && navigator.appVersion.match(/WebKit\/(\d+)/)[1] < 420) {
		// this will hold the old history states, since they can't be reliably taken from the location object
		var _unFocusHistoryLength = history.length,
			_historyStates = {}, _form,
			_recentlyAdded = false;
		
		// Setting the hash directly in Safari seems to cause odd content refresh behavior.
		// We'll use a form to submit to a #hash location instead. I'm assuming this works,
		// since I saw it done this way in SwfAddress (gotta give credit where credit it due ;-) ).
		function _createSafariSetHashForm() {
			_form = document.createElement("form");
			_form.id = "unFocusHistoryForm";
			_form.method = "get";
			document.body.insertBefore(_form,document.body.firstChild);
		}
		
		// override the old _setHash method to use the new form
		_setHash = function($newHash) {
			_historyStates[_unFocusHistoryLength] = $newHash;
			_form.action = "#" + _getHash();
			_form.submit();
		};
		
		// override the old _getHash method, since Safari doesn't update location.hash (fixed in nightlies)
		_getHash = function() {
			return _historyStates[_unFocusHistoryLength];
		};
		
		// set initial history entry
		_historyStates[_unFocusHistoryLength] = _currentHash;
		
		function addHistorySafari($newHash) {
			if (_currentHash != $newHash) {
				_currentHash = $newHash;
				_unFocusHistoryLength = history.length+1;
				_recentlyAdded = true;
				_setHash($newHash);
				_this.notifyListeners("historyChange",$newHash);
				_recentlyAdded = false;
			}
			return true;
		}
		
		// provide alternative addHistory
		_this.addHistory = function($newHash) { // adds history and bookmark hash
			// setup the form fix
			_createSafariSetHashForm();
			
			// replace with slimmer version...
			// :TODO: rethink this - it's adding an extra scope to the chain, which might
			// actually cost more at runtime than a simple if statement. Can this be done
			// without adding to the scope chain? The replaced scope holds no values. Does
			// it keep it's place in the scope chain?
			_this.addHistory = addHistorySafari;
			
			// ...do first call
			return _this.addHistory($newHash);
		};
		function _watchHistoryLength() {
			if (!_recentlyAdded) {
				var _historyLength = history.length;
				if (_historyLength != _unFocusHistoryLength) {
					_unFocusHistoryLength = _historyLength;
					
					var $newHash = _getHash();
					if (_currentHash != $newHash) {
						_currentHash = $newHash;
						_this.notifyListeners("historyChange", $newHash);
					}
				}
			}
		};
		
		// since it doesn't work, might as well cancel the location.hash check
		clearInterval(_intervalID);
		// watch the history.length prop for changes instead
		_intervalID = setInterval(_watchHistoryLength, _pollInterval);
		
	// IE 5.5+ Windows
	} else if (/*@cc_on!@*/0 && navigator.userAgent.match(/MSIE (\d+\.\d+)/)[1] >= 5.5) {
		
		// :HACK: Quick and dirty IE8 support (makes IE8 use standard timer method).
		if (document.documentMode && document.documentMode >= 8)
			return;
		
		/* iframe references */
		var _historyFrameObj, _historyFrameRef;
		
		/*
		method: _createHistoryFrame
			
			This is for IE only for now.
		*/
		function _createHistoryFrame() {
			var $historyFrameName = "unFocusHistoryFrame";
			_historyFrameObj = document.createElement("iframe");
			_historyFrameObj.setAttribute("name", $historyFrameName);
			_historyFrameObj.setAttribute("id", $historyFrameName);
			// :NOTE: _Very_ experimental
			_historyFrameObj.setAttribute("src", 'javascript:;');
			_historyFrameObj.style.position = "absolute";
			_historyFrameObj.style.top = "-900px";
			document.body.insertBefore(_historyFrameObj,document.body.firstChild);
			// get reference to the frame from frames array (needed for document.open)
			// :NOTE: there might be an issue with this according to quirksmode.org
			// http://www.quirksmode.org/js/iframe.html
			_historyFrameRef = frames[$historyFrameName];
			
			// add base history entry
			_createHistoryHTML(_currentHash, true);
		}
		
		/*
		method: _createHistoryHTML
			This is an alternative to <_setHistoryHTML> that is used by IE (and others if I can get it to work).
			This method will create the history page completely in memory, with no need to download a new file
			from the server.
		*/
		function _createHistoryHTML($newHash) {
			with (_historyFrameRef.document) {
				open("text/html");
				write("<html><head></head><body onl",
					'oad="parent.unFocus.History._updateFromHistory(\''+$newHash+'\');">',
					$newHash+"</body></html>");
				close();
			}
		}
		
		/*
		method: _updateFromHistory
			A private method that is meant to be called only from HistoryFrame.html.
			It is not meant to be used by an end user even though it is accessable as public.
		*/
			// hides the first call to the method, and sets up the real method for the rest of the calls
		function updateFromHistory($hash) {
			_currentHash = $hash;
			_this.notifyListeners("historyChange", $hash);
		}
		_this._updateFromHistory = function() {
			_this._updateFromHistory = updateFromHistory;
		};

		function addHistoryIE($newHash) { // adds history and bookmark hash
			if (_currentHash != $newHash) {
				// :NOTE: IE will create an entry if there is an achor on the page, but it
				// does not allow you to detect the state change.
				_currentHash = $newHash;
				// sets hash and notifies listeners
				_createHistoryHTML($newHash);
			}
			return true;
		};
		_this.addHistory = function($newHash) {
			// do initialization stuff on first call
			_createHistoryFrame();
			
			// replace this function with a slimmer one on first call
			_this.addHistory = addHistoryIE;
			// call the first call
			return _this.addHistory($newHash);
		};
		// anonymous method - subscribe to self to update the hash when the history is updated
		_this.addEventListener("historyChange", function($hash) { _setHash($hash) });
		
	}
}
Keeper.prototype = new unFocus.EventManager("historyChange");

return new Keeper();

})();
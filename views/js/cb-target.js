/**
 * cb-target ~ Version 1.40.0
 * @author    Serge Jamasb
 * @copyright the author
 * @license  MIT/GPL
 */
//<![CDATA[

var _custom = ['tr', 3],
	_prestacrea = ['div', 2];
	
// uncomment (only one) of the following lines to
// to affect the target element for the bpost carrier box.

// var cb_mode = _prestacrea;
// var cb_mode = _custom;


// ********************************
// *** Do NOT modify below here ***
// ********************************
function getCBT() {
	cbt = [];
	if ('undefined' !== typeof(cb_mode)) cbt = cb_mode;

	return cbt;
}
// _parent = 'div';
// _parent = 'tr';
// 
// _child = 2;
// _child = 3;
// _child = 4;
function getCBT2() {
	cbt = [];
	if ('undefined' !== typeof(_parent) && 'undefined' !== typeof(_child)) { cbt[0]=_parent; cbt[1]=_child; }
	
	return cbt;
}
//]]>
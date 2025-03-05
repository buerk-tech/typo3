/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
var __decorate=function(o,t,e,r){var i,c=arguments.length,l=c<3?t:null===r?r=Object.getOwnPropertyDescriptor(t,e):r;if("object"==typeof Reflect&&"function"==typeof Reflect.decorate)l=Reflect.decorate(o,t,e,r);else for(var p=o.length-1;p>=0;p--)(i=o[p])&&(l=(c<3?i(l):c>3?i(t,e,l):i(t,e))||l);return c>3&&l&&Object.defineProperty(t,e,l),l};import{html,css,LitElement}from"lit";import{customElement,property}from"lit/decorators.js";import Notification from"@typo3/backend/notification.js";import{lll}from"@typo3/core/lit-helper.js";export function copyToClipboard(o){if(!o.length)return console.warn("No text for copy to clipboard given."),void Notification.error(lll("copyToClipboard.error"));if(navigator.clipboard)navigator.clipboard.writeText(o).then((()=>{Notification.success(lll("copyToClipboard.success"),"",1)})).catch((()=>{Notification.error(lll("copyToClipboard.error"))}));else{const t=document.createElement("textarea");t.value=o,document.body.appendChild(t),t.focus(),t.select();try{document.execCommand("copy")?Notification.success(lll("copyToClipboard.success"),"",1):Notification.error(lll("copyToClipboard.error"))}catch{Notification.error(lll("copyToClipboard.error"))}document.body.removeChild(t)}}let CopyToClipboard=class extends LitElement{constructor(){super(),this.addEventListener("click",(o=>{o.preventDefault(),this.copyToClipboard()})),this.addEventListener("keydown",(o=>{"Enter"!==o.key&&" "!==o.key||(o.preventDefault(),this.copyToClipboard())}))}connectedCallback(){this.hasAttribute("role")||this.setAttribute("role","button"),this.hasAttribute("tabindex")||this.setAttribute("tabindex","0")}render(){return html`<slot></slot>`}copyToClipboard(){if("string"!=typeof this.text)return console.warn("No text for copy to clipboard given."),void Notification.error(lll("copyToClipboard.error"));copyToClipboard(this.text)}};CopyToClipboard.styles=[css`:host{cursor:pointer;appearance:button}`],__decorate([property({type:String})],CopyToClipboard.prototype,"text",void 0),CopyToClipboard=__decorate([customElement("typo3-copy-to-clipboard")],CopyToClipboard);export{CopyToClipboard};
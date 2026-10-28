import{l as r,u as $,_ as N,d as p,w as S,j as e,B as a,M as _}from"./app-CVvrgYdM.js";import{S as z}from"./share-2-CbT6aiNO.js";import{C as M}from"./check-CAMmUOn1.js";import{M as I}from"./AppLayout-CVfV4huS.js";/**
 * @license lucide-react v1.61.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const x={name:"copy",size:24,node:[["rect",{width:"14",height:"14",x:"8",y:"8",rx:"2",ry:"2",key:"17jyea"}],["path",{d:"M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2",key:"zix9uf"}]]};x.node;const R=r(x);/**
 * @license lucide-react v1.61.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const u={name:"mail",size:24,node:[["path",{d:"m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7",key:"132q7q"}],["rect",{x:"2",y:"4",width:"20",height:"16",rx:"2",key:"izxlao"}]]};u.node;const U=r(u);/**
 * @license lucide-react v1.61.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const y={name:"smartphone",size:24,node:[["rect",{width:"14",height:"20",x:"5",y:"2",rx:"2",ry:"2",key:"1yt0o3"}],["path",{d:"M12 18h.01",key:"mhygvu"}]]};y.node;const B=r(y);function D({path:w,title:g,kind:s}){const{basePath:f="",facebookAppId:i=""}=$().props,{touch:d}=N(),[j,c]=p.useState(!1),[l,h]=p.useState(!1),t=`${window.location.origin}${S(w,f)}`,o=`${s}: ${g}`,C=typeof navigator<"u"&&typeof navigator.share=="function";async function m(){try{await navigator.clipboard.writeText(t)}catch{const n=document.getElementById("share-link");n==null||n.select(),document.execCommand("copy")}h(!0),window.setTimeout(()=>h(!1),2e3)}async function b(){try{await navigator.share({title:o,text:o,url:t}),c(!1)}catch{}}function k(){if(d){window.location.href=`fb-messenger://share/?link=${encodeURIComponent(t)}`;return}if(i){const n=`https://www.facebook.com/dialog/send?app_id=${encodeURIComponent(i)}&link=${encodeURIComponent(t)}&redirect_uri=${encodeURIComponent(t)}`;window.open(n,"_blank","noopener,width=640,height=560");return}m(),window.open("https://www.messenger.com/","_blank","noopener")}const v=`mailto:?subject=${encodeURIComponent(o)}&body=${encodeURIComponent(`${o}

Open it here: ${t}

You may need to sign in first.`)}`;return e.jsxs(e.Fragment,{children:[e.jsxs(a,{type:"button",variant:"secondary",onClick:()=>c(!0),"aria-label":`Share this ${s.toLowerCase()}`,children:[e.jsx(z,{className:"h-4 w-4"})," Share"]}),e.jsxs(_,{open:j,title:`Share ${s.toLowerCase()}`,onOpenChange:c,children:[e.jsxs("p",{className:"text-sm text-zinc-600",children:["Send this link to a teammate. They sign in if needed, then land right on this ",s.toLowerCase(),"."]}),e.jsxs("div",{className:"mt-3 flex gap-2",children:[e.jsx("input",{id:"share-link",readOnly:!0,value:t,onFocus:n=>n.target.select(),className:"min-w-0 flex-1 rounded-lg border border-zinc-300 bg-zinc-50 px-3 py-2 text-sm text-zinc-700"}),e.jsxs(a,{type:"button",variant:"secondary",onClick:m,"aria-label":"Copy link",children:[l?e.jsx(M,{className:"h-4 w-4 text-emerald-600"}):e.jsx(R,{className:"h-4 w-4"}),e.jsx("span",{className:"hidden sm:inline",children:l?"Copied":"Copy"})]})]}),e.jsxs("div",{className:"mt-4 grid gap-2 sm:grid-cols-2",children:[e.jsxs(a,{type:"button",onClick:k,className:"bg-[#0866FF] hover:bg-[#0756d6]",children:[e.jsx(I,{className:"h-4 w-4"})," Messenger"]}),e.jsx(a,{type:"button",variant:"secondary",asChild:!0,children:e.jsxs("a",{href:v,children:[e.jsx(U,{className:"h-4 w-4"})," Email"]})}),C&&e.jsxs(a,{type:"button",variant:"secondary",onClick:b,className:"sm:col-span-2",children:[e.jsx(B,{className:"h-4 w-4"})," More apps…"]})]}),!d&&!i&&e.jsx("p",{className:"mt-3 text-xs text-zinc-500",children:"On a computer, Messenger opens in a new tab and the link is copied so you can paste it."})]})]})}export{D as S};

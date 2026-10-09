@props(['kind'])
<svg viewBox="0 0 480 300" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="editorial-illustration">
<path d="M58 253h364" stroke="currentColor" stroke-width="2" opacity=".2"/>
@if($kind === 'hands')
<path d="M113 252v-41l66-42c15-9 29-1 30 10l-24 23 67-17c28-6 39 20 17 30l-94 37" fill="#fff" stroke="currentColor" stroke-width="5" stroke-linejoin="round"/>
<path d="M363 252v-46l-58-37c-15-10-28-2-27 11l23 22-64-17c-26-6-37 20-15 30l81 37" fill="#fff" stroke="currentColor" stroke-width="5" stroke-linejoin="round"/>
<path d="M185 54h64a26 26 0 0 1 26 26v18h-42V82h-48M203 42v24" stroke="currentColor" stroke-width="8" stroke-linecap="round"/>
<path d="m250 113-8 13m29-10-6 18m-35 0-7 12" stroke="#4a9cc0" stroke-width="5" stroke-linecap="round"/>
<circle cx="337" cy="91" r="19" fill="#f2cd5a"/><circle cx="145" cy="116" r="8" stroke="currentColor" stroke-width="3"/><circle cx="317" cy="137" r="5" fill="currentColor"/>
@elseif($kind === 'waste')
<path d="m134 125 9 126h83l9-126M125 124h119M162 106h46M285 145l6 106h68l8-106M279 144h94M306 126h37" fill="#fff" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
<path d="M173 157v62m23-62v62M318 170v48m21-48v48" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
<path d="M250 53c23 3 44 15 48 43-31 0-50-13-48-43Z" fill="#9bbd8d"/><path d="M248 100c-3-25-18-43-45-43-1 30 17 47 45 43Z" fill="#c0d7aa"/><path d="m234 74 21 33" stroke="currentColor" stroke-width="3"/><circle cx="118" cy="68" r="13" fill="#f2cd5a"/>
@elseif($kind === 'water')
<path d="M76 233h326M76 251h326M102 233V125l76-48 76 48v108M155 233v-66h48v66" stroke="currentColor" stroke-width="5" stroke-linejoin="round"/>
<path d="m295 80-10 27m46-12-10 27m44-1-10 27m-64-14-10 27m48-3-10 27" stroke="#589bbc" stroke-width="6" stroke-linecap="round"/>
<path d="M263 199c15-15 29 15 44 0s29 15 44 0 29 15 44 0M263 218c15-15 29 15 44 0s29 15 44 0 29 15 44 0" stroke="currentColor" stroke-width="3"/><circle cx="359" cy="59" r="19" fill="#f2cd5a"/>
@elseif($kind === 'home')
<path d="M104 251V138l94-63 92 63v113M76 155l122-84 120 84M171 251v-72h53v72M125 165h25v28h-25M245 165h25v28h-25" fill="#fff" stroke="currentColor" stroke-width="5" stroke-linejoin="round"/>
<path d="M341 251v-97" stroke="currentColor" stroke-width="5"/><path d="M341 100c-52 0-52 77 0 77s52-77 0-77Z" fill="#a6c49b"/><path d="m330 145 11 19 13-26" stroke="currentColor" stroke-width="3"/><circle cx="287" cy="57" r="21" fill="#f2cd5a"/>
@else
<path d="M89 251V137m0 30-25-22m25 6 29-28" stroke="currentColor" stroke-width="5"/><path d="M89 72c-57 0-57 91 0 91s57-91 0-91Z" fill="#adcaa4"/>
<path d="M178 251V129a75 75 0 0 1 150 0v122M178 130h150M194 87l118 130M310 85 191 217M179 184h147M251 54v197" stroke="currentColor" stroke-width="4"/>
<path d="M326 161h24l49 90h-40l-33-66" fill="#f2cd5a" stroke="currentColor" stroke-width="4" stroke-linejoin="round"/>
@endif
</svg>

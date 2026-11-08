<x-layout :title="($mode==='create'?'Add ':'Edit ').$cfg['singular']" :subtitle="$cfg['label']">
<div class="page-head"><div><h2>{{ $mode==='create'?'Add':'Edit' }} {{ strtolower($cfg['singular']) }}</h2><p>{{ $cfg['description'] }}</p></div><div class="head-actions"><a href="{{ route('inventory.index',$type) }}" class="btn">Cancel</a></div></div>
<div class="card form-card">
<form method="POST" action="{{ $mode==='create' ? route('inventory.store',$type) : route('inventory.update',['type'=>$type,'id'=>$item->id]) }}">
@csrf @if($mode==='edit') @method('PUT') @endif
<div class="form-grid">
@foreach($cfg['fields'] as $name=>$field)
@php
$value = old($name, $item->$name ?? null);
$defaults = ['environment'=>'production','status'=>($type==='applications'?'healthy':($type==='repositories'?'active':'online')),'provider'=>'github','visibility'=>'private','engine'=>'mysql','backup_status'=>'not_configured','type'=>'server'];
if($mode==='create' && $value===null && isset($defaults[$name])) $value=$defaults[$name];
if($name==='tech_stack' && is_array($value)) $value = implode(', ', $value);
$options = $field['options'] ?? [];
if($field['type']==='owner') $options = $owners;
if($field['type']==='application') $options = $applications;
$full = in_array($field['type'], ['textarea']) || in_array($name, ['name','description','notes','url','specs']);
@endphp
<div class="field {{ $full?'full':'' }}">
<label for="{{ $name }}">{{ $field['label'] }} @if(!empty($field['required']))<span class="required">*</span>@endif</label>
@if($field['type']==='textarea')<textarea id="{{ $name }}" name="{{ $name }}" placeholder="{{ $field['placeholder'] ?? '' }}">{{ $value }}</textarea>
@elseif($field['type']==='select' || $field['type']==='owner' || $field['type']==='application')
<select id="{{ $name }}" name="{{ $name }}" @if(!empty($field['required'])) required @endif><option value="">{{ $field['type']==='select' ? 'Select…' : 'Unassigned' }}</option>@foreach($options as $optVal=>$optLabel)<option value="{{ $optVal }}" @selected((string)$value===(string)$optVal)>{{ $optLabel }}</option>@endforeach</select>
@else<input class="input" id="{{ $name }}" name="{{ $name }}" type="{{ $field['type']==='number'?'number':($field['type']==='date'?'date':($field['type']==='url'?'url':'text')) }}" value="{{ $value }}" placeholder="{{ $field['placeholder'] ?? '' }}" @if(!empty($field['required'])) required @endif @if($name==='health_score') min="0" max="100" @endif>
@endif
@if(!empty($field['help']))<div class="help">{{ $field['help'] }}</div>@endif
</div>
@endforeach
</div>
<div class="form-actions"><a href="{{ route('inventory.index',$type) }}" class="btn">Cancel</a><button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" />{{ $mode==='create'?'Create':'Save changes' }}</button></div>
</form>
</div>
</x-layout>

{!! $title !!}

@foreach ($paragraphs as $paragraph)
{!! $paragraph !!}

@endforeach
@if ($code)
{!! $code !!}

@endif
@foreach ($details as $label => $value)
{!! $label !!} : {!! $value !!}
@endforeach
@if ($details)

@endif
@if ($button)
{!! $button['label'] !!} : {!! $button['url'] !!}

@endif
@if ($note)
{!! $note !!}

@endif
--
Kekeli, un produit de {!! $footer['name'] !!}@if ($footer['address']) · {!! $footer['address'] !!}@endif

@if ($footer['phones'])
Téléphones : {!! implode(' · ', $footer['phones']) !!}
@endif
@if ($footer['email'])
E-mail : {!! $footer['email'] !!}
@endif

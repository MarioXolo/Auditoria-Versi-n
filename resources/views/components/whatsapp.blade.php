@props(['phone'])
@php $url = \App\Models\CampaignVoter::whatsappUrl($phone); @endphp
@if ($url)
    <a href="{{ $url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 whitespace-nowrap hover:text-emerald-700" title="Escribir por WhatsApp">
        <svg class="size-3.5 text-emerald-600" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 00-8.6 15.1L2 22l5-1.3A10 10 0 1012 2zm0 18a8 8 0 01-4.1-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8 8 0 1112 20zm4.4-6c-.2-.1-1.4-.7-1.7-.8s-.4-.1-.5.1-.6.8-.8.9-.3.2-.5 0a6.6 6.6 0 01-3.3-2.9c-.2-.4.2-.4.7-1.3a.4.4 0 000-.4l-.8-1.8c-.2-.5-.4-.4-.5-.4h-.5a.9.9 0 00-.6.3 2.6 2.6 0 00-.8 1.9 4.5 4.5 0 00.9 2.4 10.3 10.3 0 004 3.5c1.5.6 2 .7 2.8.6a2.3 2.3 0 001.5-1.1 1.9 1.9 0 00.1-1.1c0-.1-.2-.2-.4-.3z"/></svg>
        {{ $phone }}
    </a>
@elseif ($phone)
    <span class="whitespace-nowrap">{{ $phone }}</span>
@else
    —
@endif

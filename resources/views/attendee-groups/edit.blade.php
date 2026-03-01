@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('attendee-groups.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-900">Edit Group</h1>
    </div>

    <form method="POST" action="{{ route('attendee-groups.update', $attendeeGroup) }}" class="bg-white rounded-xl border border-gray-200 p-6 space-y-6">
        @csrf
        @method('PUT')

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="name" value="{{ old('name', $attendeeGroup->name) }}" required class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" id="description" rows="3" class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">{{ old('description', $attendeeGroup->description) }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Members</label>
            <div x-data="{ members: {{ json_encode(old('default_members', $attendeeGroup->default_members ?? [])) }} }">
                <template x-for="(member, index) in members" :key="index">
                    <div class="flex gap-2 mb-2">
                        <input type="text" :name="`default_members[${index}][name]`" x-model="member.name" placeholder="Name" class="flex-1 text-sm border border-gray-300 rounded-lg px-3 py-2">
                        <input type="email" :name="`default_members[${index}][email]`" x-model="member.email" placeholder="Email" class="flex-1 text-sm border border-gray-300 rounded-lg px-3 py-2">
                        <input type="text" :name="`default_members[${index}][role]`" x-model="member.role" placeholder="Role (optional)" class="w-32 text-sm border border-gray-300 rounded-lg px-3 py-2">
                        <button type="button" @click="members.splice(index, 1)" class="text-red-400 hover:text-red-600 text-sm px-2">Remove</button>
                    </div>
                </template>
                <button type="button" @click="members.push({name:'',email:'',role:''})" class="text-sm text-violet-600 hover:text-violet-700 font-medium mt-1">+ Add Member</button>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
            <a href="{{ route('attendee-groups.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900">Cancel</a>
            <button type="submit" class="bg-violet-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">Update Group</button>
        </div>
    </form>
</div>
@endsection

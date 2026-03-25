# antaraFlow Starter Kit — Component Guide

> Copy-paste snippets for all UI components. All snippets use design tokens from the design system.

---

## Buttons

### Primary Button
```html
<button class="inline-flex items-center gap-2 px-4 py-2 rounded-xl
               bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold transition-colors
               focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2">
    Button Label
</button>
```

### Secondary Button
```html
<button class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600
               bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-sm font-medium
               hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
    Button Label
</button>
```

### Danger Button
```html
<button class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-red-200 dark:border-red-800
               text-red-600 dark:text-red-400 text-sm font-medium
               hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
    Delete
</button>
```

### Icon Button
```html
<button class="p-2 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300
               hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
    <svg class="w-5 h-5" ...>...</svg>
</button>
```

---

## Badges

### Active / Success
```html
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
             bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400
             border border-green-200 dark:border-green-800">Active</span>
```

### Pending / Warning
```html
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
             bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400
             border border-amber-200 dark:border-amber-800">Pending</span>
```

### Error / Danger
```html
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
             bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400
             border border-red-200 dark:border-red-800">Failed</span>
```

### Neutral / Inactive
```html
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
             bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400
             border border-slate-200 dark:border-slate-600">Inactive</span>
```

### Unread count (sidebar badge)
```html
<span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full
             text-[10px] font-bold bg-red-500 text-white min-w-[18px]">3</span>
```

---

## Cards

### Standard Card
```html
<div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
    <!-- content -->
</div>
```

### Stat Card
```html
<div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
    <div class="flex items-center justify-between mb-3">
        <span class="text-sm font-medium text-slate-500 dark:text-slate-400">Label</span>
        <div class="w-9 h-9 rounded-xl bg-violet-50 dark:bg-violet-900/20 flex items-center justify-center">
            <svg class="w-5 h-5 text-violet-600 dark:text-violet-400" ...>...</svg>
        </div>
    </div>
    <p class="text-3xl font-bold text-slate-900 dark:text-slate-100">128</p>
    <p class="text-xs text-green-600 dark:text-green-400 mt-1">↑ 12% from last month</p>
</div>
```

### Interactive / Clickable Card
```html
<a href="#" class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5
                   hover:border-violet-300 dark:hover:border-violet-500 hover:shadow-sm transition-all group">
    <!-- content -->
</a>
```

---

## Tables

### Table Shell
```html
<div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Column</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                <td class="px-4 py-3 text-slate-900 dark:text-slate-100">Cell value</td>
            </tr>
        </tbody>
    </table>
</div>
```

---

## Form Inputs

### Text Input (Normal)
```html
<div>
    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Label</label>
    <input type="text" placeholder="Placeholder"
           class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600
                  bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100
                  placeholder-slate-400 dark:placeholder-slate-500 text-sm
                  focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
</div>
```

### Text Input (Error State)
```html
<div>
    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Label</label>
    <input type="text"
           class="w-full px-3 py-2.5 rounded-xl border border-red-300 dark:border-red-600
                  bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 text-sm
                  focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors">
    <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">Error message here.</p>
</div>
```

### Select
```html
<select class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600
               bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 text-sm
               focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
    <option>Option 1</option>
</select>
```

### Textarea
```html
<textarea rows="4"
          class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600
                 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100
                 placeholder-slate-400 dark:placeholder-slate-500 text-sm
                 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors resize-y"></textarea>
```

### Toggle Switch (Alpine.js)
```html
<button type="button" x-data="{ on: false }" @click="on = !on"
        :class="on ? 'bg-violet-600' : 'bg-slate-200 dark:bg-slate-600'"
        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors
               focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2">
    <span :class="on ? 'translate-x-6' : 'translate-x-1'"
          class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
</button>
```

---

## Empty State

```html
<div class="py-12 text-center">
    <svg class="w-12 h-12 text-slate-300 dark:text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
    </svg>
    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Nothing here yet</p>
    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Items will appear here when added</p>
    <button class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl
                   bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold transition-colors">
        Create First Item
    </button>
</div>
```

---

## Alert / Flash Messages

### Success
```html
<div class="mx-6 mt-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800
            text-green-700 dark:text-green-300 px-4 py-3 rounded-lg text-sm">
    Action completed successfully.
</div>
```

### Error
```html
<div class="mx-6 mt-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800
            text-red-700 dark:text-red-300 px-4 py-3 rounded-lg text-sm">
    Something went wrong. Please try again.
</div>
```

### Warning
```html
<div class="mx-6 mt-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800
            text-amber-700 dark:text-amber-300 px-4 py-3 rounded-lg text-sm">
    Please review before proceeding.
</div>
```

---

## Tabs (Alpine.js)

```html
<div x-data="{ tab: 'first' }">
    <!-- Tab nav -->
    <div class="border-b border-slate-200 dark:border-slate-700 px-4">
        <nav class="flex gap-0 -mb-px">
            <button @click="tab = 'first'"
                    :class="tab === 'first'
                        ? 'border-violet-600 text-violet-600 dark:text-violet-400 dark:border-violet-400'
                        : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 hover:border-slate-300'"
                    class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">Tab 1</button>
            <button @click="tab = 'second'"
                    :class="tab === 'second'
                        ? 'border-violet-600 text-violet-600 dark:text-violet-400 dark:border-violet-400'
                        : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 hover:border-slate-300'"
                    class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">Tab 2</button>
        </nav>
    </div>
    <!-- Tab content -->
    <div class="p-6">
        <div x-show="tab === 'first'">Content 1</div>
        <div x-show="tab === 'second'">Content 2</div>
    </div>
</div>
```

---

## Page Header Pattern

```html
<div class="flex items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Page Title</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Subtitle or description</p>
    </div>
    <button class="inline-flex items-center gap-2 px-4 py-2 rounded-xl
                   bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        New Item
    </button>
</div>
```

---

## Back Link Pattern

```html
<a href="list.html"
   class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400
          hover:text-slate-700 dark:hover:text-slate-200 transition-colors">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
    </svg>
    Back to List
</a>
```

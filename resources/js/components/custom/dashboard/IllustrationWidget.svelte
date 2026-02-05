<script lang="ts">
    import type { Component } from 'svelte';

    interface Props {
        title: string;
        description: string;
        color: 'blue' | 'orange' | 'purple' | 'green';
        icon: any;
        stats?: string;
    }

    let { title, description, color, icon: Icon, stats }: Props = $props();

    const colors = {
        blue: 'from-blue-500 to-blue-600',
        orange: 'from-orange-400 to-orange-500',
        purple: 'from-purple-500 to-purple-600',
        green: 'from-emerald-500 to-emerald-600'
    };

    const bgColors = {
        blue: '#3b82f6',
        orange: '#f97316',
        purple: '#a855f7',
        green: '#10b981'
    };
</script>

<div class="illustration-widget" style="--bg-color: {bgColors[color]}">
    <div class="content">
        <h3 class="title">{title}</h3>
        <p class="description">{description}</p>
        {#if stats}
            <div class="stats">{stats}</div>
        {/if}
    </div>
    <div class="icon-wrapper">
        <Icon size={48} strokeWidth={1.5} />
    </div>
    
    <!-- Decorative Circle -->
    <div class="decorative-circle"></div>
</div>

<style>
    .illustration-widget {
        background: var(--bg-color);
        padding: 1.5rem;
        border-radius: 16px;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
        overflow: hidden;
        min-height: 120px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s;
        cursor: pointer;
    }

    .illustration-widget:hover {
        transform: translateY(-4px);
    }

    .content {
        position: relative;
        z-index: 2;
        max-width: 60%;
    }

    .title {
        font-size: 1.125rem;
        font-weight: 700;
        margin: 0;
    }

    .description {
        font-size: 0.8125rem;
        opacity: 0.9;
        margin: 0.5rem 0 0 0;
        line-height: 1.4;
    }

    .stats {
        margin-top: 0.75rem;
        font-size: 0.75rem;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.2);
        padding: 0.25rem 0.625rem;
        border-radius: 20px;
        display: inline-block;
    }

    .icon-wrapper {
        position: relative;
        z-index: 2;
        background: rgba(255, 255, 255, 0.2);
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(4px);
    }

    .decorative-circle {
        position: absolute;
        top: -20px;
        right: -20px;
        width: 140px;
        height: 140px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        z-index: 1;
    }
</style>

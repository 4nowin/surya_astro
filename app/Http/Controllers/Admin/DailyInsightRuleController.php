<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyInsightRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DailyInsightRuleController extends Controller
{
    /**
     * Display a listing of daily insight rules with filtering.
     */
    public function index(Request $request): View
    {
        $query = DailyInsightRule::query();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('planet')) {
            $query->where('planet', $request->planet);
        }

        if ($request->filled('house')) {
            $query->where('house', (int) $request->house);
        }

        if ($request->filled('tone')) {
            $query->where('tone', $request->tone);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active === '1');
        }

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('rule_identifier', 'like', $search)
                    ->orWhere('headline_en', 'like', $search)
                    ->orWhere('headline_hi', 'like', $search);
            });
        }

        $rules = $query->orderBy('category')
            ->orderBy('planet')
            ->orderBy('house')
            ->orderBy('priority', 'desc')
            ->paginate(15)
            ->withQueryString();

        $categories = DailyInsightRule::CATEGORIES;
        $planets = DailyInsightRule::PLANETS;
        $tones = DailyInsightRule::TONES;

        return view('admin.daily_insight_rules.index', compact('rules', 'categories', 'planets', 'tones'));
    }

    /**
     * Show form to create a new rule.
     */
    public function create(): View
    {
        $categories = DailyInsightRule::CATEGORIES;
        $planets = DailyInsightRule::PLANETS;
        $tones = DailyInsightRule::TONES;

        return view('admin.daily_insight_rules.create', compact('categories', 'planets', 'tones'));
    }

    /**
     * Store a newly created rule.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rule_identifier' => ['required', 'string', 'max:64', 'unique:daily_insight_rules,rule_identifier'],
            'category' => ['required', 'string', 'in:' . implode(',', array_keys(DailyInsightRule::CATEGORIES))],
            'planet' => ['required', 'string', 'in:' . implode(',', array_keys(DailyInsightRule::PLANETS))],
            'house' => ['required', 'integer', 'between:1,12'],
            'is_retrograde' => ['nullable', 'boolean'],
            'tone' => ['required', 'string', 'in:' . implode(',', array_keys(DailyInsightRule::TONES))],
            'headline_en' => ['required', 'string', 'max:191'],
            'headline_hi' => ['required', 'string', 'max:191'],
            'interpretation_en' => ['required', 'string'],
            'interpretation_hi' => ['required', 'string'],
            'priority' => ['required', 'integer', 'between:1,100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->has('is_active');
        DailyInsightRule::create($validated);

        return redirect()->route('daily-insight-rules.index')
            ->with('success', 'Interpretation rule created successfully.');
    }

    /**
     * Show form to edit an existing rule.
     */
    public function edit(DailyInsightRule $dailyInsightRule): View
    {
        $rule = $dailyInsightRule;
        $categories = DailyInsightRule::CATEGORIES;
        $planets = DailyInsightRule::PLANETS;
        $tones = DailyInsightRule::TONES;

        return view('admin.daily_insight_rules.edit', compact('rule', 'categories', 'planets', 'tones'));
    }

    /**
     * Update an existing rule.
     */
    public function update(Request $request, DailyInsightRule $dailyInsightRule): RedirectResponse
    {
        $validated = $request->validate([
            'rule_identifier' => ['required', 'string', 'max:64', 'unique:daily_insight_rules,rule_identifier,' . $dailyInsightRule->id],
            'category' => ['required', 'string', 'in:' . implode(',', array_keys(DailyInsightRule::CATEGORIES))],
            'planet' => ['required', 'string', 'in:' . implode(',', array_keys(DailyInsightRule::PLANETS))],
            'house' => ['required', 'integer', 'between:1,12'],
            'is_retrograde' => ['nullable', 'boolean'],
            'tone' => ['required', 'string', 'in:' . implode(',', array_keys(DailyInsightRule::TONES))],
            'headline_en' => ['required', 'string', 'max:191'],
            'headline_hi' => ['required', 'string', 'max:191'],
            'interpretation_en' => ['required', 'string'],
            'interpretation_hi' => ['required', 'string'],
            'priority' => ['required', 'integer', 'between:1,100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->has('is_active');
        $dailyInsightRule->update($validated);

        return redirect()->route('daily-insight-rules.index')
            ->with('success', 'Interpretation rule updated successfully.');
    }

    /**
     * Toggle the active status of a rule.
     */
    public function toggleActive(DailyInsightRule $rule): RedirectResponse
    {
        $rule->is_active = !$rule->is_active;
        $rule->save();

        $status = $rule->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Rule [{$rule->rule_identifier}] has been {$status}.");
    }

    /**
     * Delete an existing rule.
     */
    public function destroy(DailyInsightRule $dailyInsightRule): RedirectResponse
    {
        $dailyInsightRule->delete();

        return redirect()->route('daily-insight-rules.index')
            ->with('success', 'Interpretation rule deleted successfully.');
    }
}

{{-- FILE: resources/views/admin/cp/analyses/partials/faq-row.blade.php --}}
<div class="faq-row row g-2 mb-2 align-items-start">
    <div class="col-5">
        <input type="text" name="faq_question[]" class="form-control form-control-sm"
               placeholder="Question">
    </div>
    <div class="col-6">
        <textarea name="faq_answer[]" class="form-control form-control-sm"
                  rows="2" placeholder="Answer"></textarea>
    </div>
    <div class="col-1">
        <button type="button" class="btn btn--danger btn--sm" style="padding:4px 8px;"
                onclick="this.closest('.faq-row').remove()">
            <i class="las la-trash"></i>
        </button>
    </div>
</div>
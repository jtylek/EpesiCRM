// A multi-select (Customers, Sales Team, ...) keeps its typed search after an
// option is picked, so picking a second one means deleting the text first.
// Clear it once Filament has taken the choice, by click or by Enter.
{
    const clearSearch = (panel) => {
        const input = panel?.querySelector('.fi-select-input-search-ctn input')

        if (input && input.value !== '') {
            input.value = ''
            input.dispatchEvent(new Event('input', { bubbles: true }))
        }
    }

    document.addEventListener(
        'click',
        (event) => {
            const option = event.target.closest?.('.fi-select-input-option')

            if (option) {
                const panel = option.closest('.fi-dropdown-panel')

                setTimeout(() => clearSearch(panel), 0)
            }
        },
        true,
    )

    document.addEventListener(
        'keydown',
        (event) => {
            if (event.key !== 'Enter') {
                return
            }

            const panel = event.target.closest?.('.fi-dropdown-panel')

            if (panel?.querySelector('.fi-select-input-search-ctn')) {
                setTimeout(() => clearSearch(panel), 0)
            }
        },
        true,
    )
}

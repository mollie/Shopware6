import Plugin from '../Plugin';

export default class AccountSubscriptionsPlugin extends Plugin {

    init() {

        const toggleButtons = document.querySelectorAll('.subscription-hide-btn');

        if (toggleButtons.length === 0) {
            return;
        }

        toggleButtons.forEach((button) => {

            this.updateToggleButtons(button);

            button.addEventListener('click', () => {
                this.updateToggleButtons(button);
            });
        }
        );
    }

    updateToggleButtons(button) {
        const hideText = button.querySelector('.subscription-hide-btn-text');
        const viewText = button.querySelector('.subscription-view-btn-text');

        if (button.classList.contains('collapsed')) {
            hideText.classList.add('d-none');
            viewText.classList.remove('d-none');
        } else {
            hideText.classList.remove('d-none');
            viewText.classList.add('d-none');
        }
    }
}




export default class CsrfAjaxMode
{
    constructor(csrf) {
        this.csrf = csrf;
    }

    isActive(){
        if(this.csrf === undefined){
            return false;
        }
        if(this.csrf.enabled === false){
            return false;
        }
        if(this.csrf.mode !== 'ajax'){
            return false;
        }
        return true;
    }
}
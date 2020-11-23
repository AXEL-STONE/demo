BX.ready(function(){
    BX.bindDelegate(
        document.body, 'click', {className: 'delete-city' },
        function(e){
            var id = this.getAttribute('data-delete_id_city');
            var ulr = location.href;
            ulr = BX.util.remove_url_param(ulr, ['DELETE_CITY']);
            ulr = BX.util.add_url_param(ulr, {'DELETE_CITY': id});
            location.href = ulr;
            if(!e)  e = window.event;
            return BX.PreventDefault(e);
        }
    );
});

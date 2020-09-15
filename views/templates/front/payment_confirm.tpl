
  <section>
        <form id="payment_confirmation" action="https://testsecureacceptance.cybersource.com/pay" method="post"/>
            <fieldset id="confirmation">
            <legend>Review Payment Details</legend>
                <div>
                    {foreach from=$params key=name item=value}
                        <div><span class="fieldName">{$name}: </span><span class="fieldValue">{$value}</span></div>
                    {/foreach}
                    <div><span class="fieldName">signature: </span><span class="fieldValue">{$signature}</span></div>                     
                </div>
            </fieldset>
            {foreach from=$params key=name item=value}
                <input type="hidden" id="{$name}" name="{$name}" value="{$value}"/>
            {/foreach}
            <input type="hidden" id="signature" name="signature" value="{$signature}"/>
            <input type="submit" id="submit" value="Confirm"/>
        </form>
  </section>

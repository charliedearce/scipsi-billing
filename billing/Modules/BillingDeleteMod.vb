Imports System.Data
Imports System.Data.SqlClient
Module BillingDeleteMod
    Public Sub vItemDelete()

        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        myCmd = New SqlCommand("SELECT it_id,it_qty,it_service,it_cargo FROM tbl_item_trans WHERE it_bill_num = '" & frmBilling.txtBillno.Text & "'", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        frmBillingDelete.dgDelete.DataSource = dt.DefaultView
        frmBillingDelete.dgDelete.Focus()
       
        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub ItemDelete()
        myCon.open()



        myCmd = New SqlCommand("DELETE FROM tbl_item_trans WHERE it_id = '" & frmBillingDelete.txtItemid.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        frmBilling.txtNotice.Text = "Item Successfully Deleted!"

        myCmd.Dispose()
        myCon.close()
    End Sub
End Module

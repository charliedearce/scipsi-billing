Imports System.Data
Imports System.Data.SqlClient

Module ORDeleteMod

    Public Sub vBillDelete()

        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)
        With frmORBillDelete
            myCmd = New SqlCommand("SELECT ot_id,ot_bill_no FROM tbl_orbill_trans WHERE ot_or_no = '" & frmor.txtOrno.Text & "'", myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            If myAdapter.Fill(dt) = 0 Then
                frmORBillDelete.txtIBillid.Text = ""
            End If
            .dgDelete.DataSource = dt.DefaultView
        End With
        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub BillDelete()
        myCon.open()

        myCmd = New SqlCommand("DELETE FROM tbl_orbill_trans WHERE ot_id= '" & frmORBillDelete.txtIBillid.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        frmBilling.txtNotice.Text = "Bill Transaction Successfully Deleted!"

        myCmd.Dispose()
        myCon.close()
    End Sub


End Module

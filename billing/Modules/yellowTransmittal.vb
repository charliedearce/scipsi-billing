Imports System.Data
Imports System.Data.SqlClient
Module yellowTransmittal
    Dim a As Date
    Dim b As Integer
    Dim c As String
    Dim d As String

    Dim f As String
    Dim g As String
    Public Sub additemTransmittal()
        Try
            Dim exists As Boolean = False
            For Each itm As DataGridViewRow In frmYtrans.dgYtransmittal.Rows
                If itm.Cells(1).Value = frmTransmitBillno.txtBillnum.Text Then
                    exists = True
                End If
            Next
            Dim change As Integer = frmTransmitBillno.txtBillnum.Text
            frmTransmitBillno.txtBillnum.Text = String.Format("{0:0000000000}", change.ToString("0000000000"))
            myCon.open()
            Dim mycmd = New SqlCommand("Select * FROM tbl_bill_trans WHERE bt_status = 'FALSE' AND bt_bill_num = '" & frmTransmitBillno.txtBillnum.Text & "'", myCon)
            Dim myReader As SqlDataReader = mycmd.ExecuteReader()

            If exists = True Then
                frmYtrans.txtNotice.Text = "Bill number is on process."
                frmTransmitBillno.txtBillnum.Focus()
            Else
                If myReader.HasRows Then
                    If myReader.Read Then
                        a = myReader("bt_date")
                        b = myReader("bt_bill_num")
                        c = myReader("bt_vessel")
                        d = myReader("bt_voyage")
                        f = myReader("bt_account")
                        g = myReader("bt_type")
                    End If


                    myReader.Close()
                    mycmd.dispose()

                    Dim mycmd1 = New SqlCommand("INSERT INTO tbl_ymital_item (yi_date,yi_billnum,yi_vessel,yi_voyage,yi_particulars,yi_type,yi_cusname,yi_transnum) VALUES ('" & a.ToString("MM/dd/yyyy") & "','" & b & "','" & c & "','" & d & "','" & frmTransmitBillno.txtService.Text & "','" & g & "','" & f & "','" & frmYtrans.txtYtransno.Text & "')", myCon)
                    mycmd1.ExecuteNonQuery()
                    frmYtrans.txtNotice.Text = ""
                    mycmd1.dispose()
                    frmTransmitBillno.Dispose()

                Else
                    frmYtrans.txtNotice.Text = "Bill number not found."
                End If
                'myCon.close()
            End If

        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            myCon.close()
        End Try
    End Sub

    Public Sub ViewYTrans()
        Try
            myCon.open()
            Dim dt As New DataTable
            Dim ds As New DataSet
            ds.Tables.Add(dt)
            myCmd = New SqlCommand("SELECT yi_date,yi_billnum,yi_particulars,yi_type,yi_vessel,yi_voyage,yi_cusname,yi_id FROM tbl_ymital_item WHERE yi_transnum = '" & frmYtrans.txtYtransno.Text & "'", myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            If myAdapter.Fill(dt) <> 0 Then
                With frmYtrans
                    If .txtindi.Text = "1" Then
                        .txtYtransno.Enabled = False
                        .btnDelBill.Enabled = True
                        .btnSearchTrans.Enabled = False
                        .btnDelTrans.Enabled = False
                        .btnClose.Enabled = False
                        .btnAddBill.Enabled = False
                        If .btnNewTrans.Enabled = False Then
                            .btnAddBill.Enabled = True
                        End If
                    Else
                        .btnDelTrans.Enabled = True
                        .btnDelBill.Enabled = False
                    End If
                End With
            Else

                With frmYtrans
                    If .btnNewTrans.Enabled = True Then
                        .txtYtransno.Enabled = False
                    Else
                        .txtYtransno.Enabled = True
                    End If
                    .btnDelBill.Enabled = False
                    .btnDelTrans.Enabled = False
                    .btnSearchTrans.Enabled = True
                    .btnClose.Enabled = True
                End With
            End If
            With frmYtrans
                .dgYtransmittal.DataSource = dt.DefaultView
            End With

            myAdapter.Dispose()
            myCmd.Dispose()
            myCon.close()
        Catch ex As Exception
            MsgBox(ex.Message)
        End Try
       

    End Sub

    Public Sub DelYtranstItem()
        myCon.open()
        myCmd = New SqlCommand("DELETE FROM tbl_ymital_item WHERE yi_id = '" & frmTransDelBill.txtItemid.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        frmYtrans.txtNotice.Text = "Invoice Successfully Deleted!"
        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub DeleteYmitalBill()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        myCmd = New SqlCommand("SELECT yi_id,yi_billnum FROM tbl_ymital_item WHERE yi_transnum = '" & frmYtrans.txtYtransno.Text & "'", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        With frmTransDelBill
            .dgYtransDel.DataSource = dt.DefaultView
            .dgYtransDel.Columns(0).Visible = False
            .dgYtransDel.Focus()
            On Error Resume Next
            .dgYtransDel.Rows.Item(0).Selected = True
            myCmd.Dispose()
            myCon.close()
        End With

    End Sub

    Public Sub SaveYmital()
        Try
            myCon.open()
            Dim myCmd1 = New SqlCommand("SELECT * FROM tbl_ymital_trans WHERE yt_transnum = '" & frmYtrans.txtYtransno.Text & "'", myCon)
            Dim myReader1 As SqlDataReader = myCmd1.ExecuteReader()

            If myReader1.HasRows Then
                myReader1.Close()
                frmYtrans.txtindi.Text = 0
            Else
                myReader1.Close()
                    myCmd = New SqlCommand("INSERT INTO tbl_ymital_trans (yt_transnum,yt_date) VALUES ('" & frmYtrans.txtYtransno.Text & "','" & frmYtrans.txtBilldate.Text & "')", myCon)
                frmYtrans.txtindi.Text = 0
                myCmd.ExecuteNonQuery()
                myCmd.Dispose()
            End If
            myReader1.Close()
            myCmd1.dispose()
            myCon.close()
        Catch ex As Exception
            MsgBox(ex.Message)
        End Try

    End Sub

    Public Sub YtransDel()
        myCon.open()
        myCmd = New SqlCommand("DELETE FROM tbl_ymital_trans WHERE yt_transnum = '" & frmYtrans.txtYtransno.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        myCmd.Dispose()
        Dim myCmd1 = New SqlCommand("DELETE FROM tbl_ymital_item WHERE yi_transnum = '" & frmYtrans.txtYtransno.Text & "'", myCon)
        myCmd1.ExecuteNonQuery()
        With frmYtrans
            .txtYtransno.Text = ""
            .txtBilldate.Text = ""
            .txtNotice.Text = "Transmial has been deleted."
        End With
        myCmd1.Dispose()
        myCon.close()
    End Sub

    Public Sub SearchYmital()
        myCon.open()
        myCmd = New SqlCommand(String.Format("SELECT * FROM tbl_ymital_trans WHERE yt_transnum = '{0}'", frmSearchYmital.txtYmitalno.Text), myCon)
        myReader = myCmd.ExecuteReader()
        If myReader.HasRows Then
            If myReader.Read Then
                With frmYtrans
                    .txtYtransno.Text = myReader("yt_transnum")
                    .txtBilldate.Text = myReader("yt_date")
                    .txtYtransno.Enabled = False
                    .txtBilldate.Enabled = False
                    .btnAddBill.Enabled = False
                    .txtindi.Text = 0
                    .btnNewTrans.Enabled = True
                    .btnEdit.Enabled = True
                    frmSearchYmital.Dispose()
                End With
            End If
            myCon.close()
        Else
            frmYtrans.txtNotice.Text = "Transmittal # not found."
        End If
        myReader.Close()
        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub UpdateYmital()
        Try
            myCon.open()
            myCmd = New SqlCommand(String.Format("UPDATE tbl_ymital_trans SET yt_date ='{0}' WHERE yt_transnum ='{1}'", frmYtrans.txtBilldate.Text, frmYtrans.txtYtransno.Text), myCon)
            myCmd.ExecuteNonQuery()
            myCmd.Dispose()
            myCon.close()
        Catch ex As Exception
            MsgBox(ex.Message)
        End Try
    End Sub
End Module

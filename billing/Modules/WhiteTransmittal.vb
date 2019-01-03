Imports System.Data
Imports System.Data.SqlClient
Module WhiteTransmittal
    Dim mydate As Date
    Dim mycode As String
    Dim mycname As String
    Dim myperiod As String
    Dim myincome As Double
    Dim myhtax As Double
    Dim myor As Integer


    Public Sub additemWhiteTransmittal()
        Try
            Dim exists As Boolean = False
            For Each itm As DataGridViewRow In frmWtrans.dgWtransmittal.Rows
                If itm.Cells(5).Value = frmWtransOrno.txtOrnum.Text Then
                    exists = True
                End If
            Next
            Dim change As Integer = frmWtransOrno.txtOrnum.Text
            frmWtransOrno.txtOrnum.Text = String.Format("{0:0000000000}", change.ToString("0000000000"))
            myCon.open()
            Dim mycmd = New SqlCommand("Select * FROM tbl_or_trans WHERE or_status = 'FALSE' AND or_num = '" & frmWtransOrno.txtOrnum.Text & "'", myCon)
            Dim myReader As SqlDataReader
            myReader = mycmd.ExecuteReader()

            If exists = True Then
                frmWtrans.txtNotice.Text = "Bill number is on process."
                frmWtransOrno.txtOrnum.Focus()
            Else
                If myReader.HasRows Then
                    If myReader.Read Then
                        mycode = myReader("or_acc_num")
                        mycname = myReader("or_accname")
                        myperiod = myReader("or_period")
                        myincome = myReader("or_bill_amount") - myReader("or_bill_vat")
                        myhtax = myReader("or_htax")
                        myor = myReader("or_num")
                        mydate = myReader("or_date")
                    End If


                    myReader.Close()
                    mycmd.dispose()

                    Dim mycmd1 = New SqlCommand("INSERT INTO tbl_wmital_item (wi_transnum,wi_ccode,wi_cname,wi_period,wi_bir,wi_income,wi_htax,wi_ornum,wi_date) VALUES ('" & _
                                                frmWtrans.txtWtransno.Text & "','" & mycode & "','" & mycname & "','" & myperiod & "','2307','" & myincome & "','" & myhtax & "','" & myor _
                                                & "','" & mydate.ToString("MM/dd/yyyy") & "')", myCon)
                    mycmd1.ExecuteNonQuery()
                    frmWtrans.txtNotice.Text = ""
                    mycmd1.dispose()
                    frmWtransOrno.Dispose()

                Else
                    frmWtrans.txtNotice.Text = "OR number not found."
                End If
                myCon.close()
            End If

        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            myCon.close()
        End Try
    End Sub

    Public Sub ViewWTrans()
        Try
            myCon.open()
            Dim dt As New DataTable
            Dim ds As New DataSet
            ds.Tables.Add(dt)
            myCmd = New SqlCommand(String.Format("SELECT wi_ccode,wi_cname,wi_period,wi_income,wi_htax,wi_ornum,wi_date,wi_id FROM tbl_wmital_item WHERE wi_transnum = '{0}'", frmWtrans.txtWtransno.Text), myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            If myAdapter.Fill(dt) <> 0 Then
                With frmWtrans
                    If .txtindi.Text = "1" Then
                        .txtWtransno.Enabled = False
                        .btnDelOr.Enabled = True
                        .btnSearchTrans.Enabled = False
                        .btnDelTrans.Enabled = False
                        .btnClose.Enabled = False
                        .btnAddOr.Enabled = False
                        If .btnNewTrans.Enabled = False Then
                            .btnAddOr.Enabled = True
                        End If
                    Else
                        .btnDelTrans.Enabled = True
                        .btnDelOr.Enabled = False
                    End If
                End With
            Else

                With frmWtrans
                    If .btnNewTrans.Enabled = True Then
                        .txtWtransno.Enabled = False
                    Else
                        .txtWtransno.Enabled = True
                    End If
                    .btnDelOr.Enabled = False
                    .btnDelTrans.Enabled = False
                    .btnSearchTrans.Enabled = True
                    .btnClose.Enabled = True
                End With
            End If

            frmWtrans.dgWtransmittal.DataSource = dt.DefaultView


            myAdapter.Dispose()
            myCmd.Dispose()
            myCon.close()
        Catch ex As Exception
            MsgBox(ex.Message)
        End Try
        

    End Sub

    Public Sub ViewWTransDel()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)
        myCmd = New SqlCommand("SELECT wi_id,wi_ornum FROM tbl_wmital_item WHERE wi_transnum = '" & frmWtrans.txtWtransno.Text & "'", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        frmWTransDelOr.dgWtransDel.DataSource = dt.DefaultView
        myAdapter.Dispose()
        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub DelWtranstItem()
        myCon.open()
        myCmd = New SqlCommand("DELETE FROM tbl_wmital_item WHERE wi_id = '" & frmWTransDelOr.txtItemid.Text & "'", myCon)
        myCmd.ExecuteNonQuery()
        frmYtrans.txtNotice.Text = "OR Successfully Deleted!"
        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub SaveWmital()
        Try
            myCon.open()
            Dim myCmd1 = New SqlCommand("SELECT * FROM tbl_wmital_trans WHERE wt_transnum = '" & frmWtrans.txtWtransno.Text & "'", myCon)
            Dim myReader1 As SqlDataReader = myCmd1.ExecuteReader()

            If myReader1.HasRows Then
                myReader1.Close()
                frmWtrans.txtindi.Text = 0
            Else
                myReader1.Close()
                myCmd = New SqlCommand("INSERT INTO tbl_wmital_trans (wt_transnum,wt_date) VALUES ('" & frmWtrans.txtWtransno.Text & "','" & frmWtrans.txtBilldate.Text & "')", myCon)
                myCmd.ExecuteNonQuery()
                myCmd.Dispose()
                frmWtrans.txtindi.Text = 0
            End If
            myReader1.Close()
            myCmd1.dispose()
            myCon.close()
        Catch ex As Exception
            MsgBox(ex.Message)
        End Try
    End Sub

    Public Sub UpdateWmital()
        Try
            myCon.open()
            myCmd = New SqlCommand(String.Format("UPDATE tbl_wmital_trans SET wt_date ='{0}' WHERE wt_transnum ='{1}'", frmWtrans.txtBilldate.Text, frmWtrans.txtWtransno.Text), myCon)
            myCmd.ExecuteNonQuery()
            myCmd.Dispose()
            myCon.close()
        Catch ex As Exception
            MsgBox(ex.Message)
        End Try
    End Sub

    Public Sub Updatehtax()
        Try
            myCon.open()
            myCmd = New SqlCommand(String.Format("UPDATE tbl_wmital_item SET wi_htax ='{0}' WHERE wi_ornum ='{1}'", frmedithtax.txtHtax.Text, frmedithtax.lblbillno.Text), myCon)
            myCmd.ExecuteNonQuery()
            myCmd.Dispose()
            myCon.close()
        Catch ex As Exception
            MsgBox(ex.Message)
        End Try
    End Sub

    Public Sub WtransDel()
        myCon.open()
        myCmd = New SqlCommand(String.Format("DELETE FROM tbl_wmital_trans WHERE wt_transnum = '{0}'", frmWtrans.txtWtransno.Text), myCon)
        myCmd.ExecuteNonQuery()
        myCmd.Dispose()
        Dim myCmd1 = New SqlCommand(String.Format("DELETE FROM tbl_wmital_item WHERE wi_transnum = '{0}'", frmWtrans.txtWtransno.Text), myCon)
        myCmd1.ExecuteNonQuery()
        With frmWtrans
            .txtWtransno.Text = ""
            .txtBilldate.Text = ""
            .txtNotice.Text = "Transmial has been deleted."
        End With
        myCmd1.Dispose()
        myCon.close()
    End Sub

    Public Sub SearchWmital()
        myCon.open()
        myCmd = New SqlCommand(String.Format("SELECT * FROM tbl_wmital_trans WHERE wt_transnum = '{0}'", frmSearchWmital.txtWmitalno.Text), myCon)
        myReader = myCmd.ExecuteReader()
        If myReader.HasRows Then
            If myReader.Read Then
                With frmWtrans
                    .txtWtransno.Text = myReader("wt_transnum")
                    .txtBilldate.Text = myReader("wt_date")
                    .txtWtransno.Enabled = False
                    .txtBilldate.Enabled = False
                    .txtindi.Text = 0
                    .btnNewTrans.Enabled = True
                    .btnEdit.Enabled = True
                    frmSearchWmital.Dispose()
                End With
            End If
            myCon.close()
        Else
            frmWtrans.txtNotice.Text = "Transmittal # not found."
        End If
        myReader.Close()
        myCmd.Dispose()
        myCon.close()
    End Sub
End Module
